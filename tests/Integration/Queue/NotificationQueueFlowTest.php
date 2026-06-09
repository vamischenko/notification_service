<?php

declare(strict_types=1);

namespace Tests\Integration\Queue;

use App\Domain\Notification\Contracts\NotificationGatewayInterface;
use App\Domain\Notification\DataTransferObjects\NotificationDTO;
use App\Domain\Notification\DataTransferObjects\SendResultDTO;
use App\Domain\Notification\Enums\NotificationStatus;
use App\Domain\Notification\Models\Notification;
use App\Domain\Notification\Models\NotificationBatch;
use App\Domain\Notification\Models\Recipient;
use App\Jobs\ProcessNotificationJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationQueueFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Full pipeline: POST → job → delivered in DB */
    public function test_sms_notification_goes_through_full_pipeline(): void
    {
        Queue::fake();

        $recipient = Recipient::factory()->create([
            'phone' => '+79991234567',
        ]);

        // Bind deterministic SMS gateway
        $this->app->bind('gateway.sms', fn () => new class implements NotificationGatewayInterface {
            public function send(NotificationDTO $dto): SendResultDTO
            {
                return new SendResultDTO(
                    success:           true,
                    providerMessageId: 'test_sms_001',
                    isDelivered:       true,
                );
            }
        });

        $response = $this->postJson('/api/v1/notifications/batch', [
            'channel'       => 'sms',
            'priority'      => 'transactional',
            'message_text'  => 'Test OTP: 9999',
            'recipient_ids' => [$recipient->id],
        ]);

        $response->assertStatus(202);
        $batchId = $response->json('data.batch_id');

        $this->assertDatabaseHas('notifications', [
            'recipient_id' => $recipient->id,
            'status'       => 'queued',
            'channel'      => 'sms',
        ]);

        $notification = Notification::where('recipient_id', $recipient->id)->firstOrFail();

        $job = new ProcessNotificationJob($notification->id);
        app()->call([$job, 'handle']);
        $notification->refresh();

        $this->assertEquals(NotificationStatus::DELIVERED, $notification->status);
        $this->assertEquals('test_sms_001', $notification->provider_message_id);
        $this->assertNotNull($notification->sent_at);
        $this->assertNotNull($notification->delivered_at);
    }

    /** Full pipeline for email channel */
    public function test_email_notification_pipeline(): void
    {
        $recipient = Recipient::factory()->create([
            'email' => 'test@example.com',
        ]);

        $this->app->bind('gateway.email', fn () => new class implements NotificationGatewayInterface {
            public function send(NotificationDTO $dto): SendResultDTO
            {
                return new SendResultDTO(
                    success:           true,
                    providerMessageId: 'test_email_001',
                    isDelivered:       false,
                );
            }
        });

        $response = $this->postJson('/api/v1/notifications/batch', [
            'channel'       => 'email',
            'priority'      => 'marketing',
            'message_text'  => 'Sale notification',
            'recipient_ids' => [$recipient->id],
        ]);

        $response->assertStatus(202);

        $notification = Notification::where('recipient_id', $recipient->id)->firstOrFail();
        ProcessNotificationJob::dispatchSync($notification->id);
        $notification->refresh();

        // isDelivered=false → status should be 'sent', not 'delivered'
        $this->assertEquals(NotificationStatus::SENT, $notification->status);
        $this->assertNull($notification->delivered_at);
    }

    /** Batch counters are updated correctly */
    public function test_batch_counters_update_after_processing(): void
    {
        $recipients = Recipient::factory(3)->create();

        $this->app->bind('gateway.sms', fn () => new class implements NotificationGatewayInterface {
            public function send(NotificationDTO $dto): SendResultDTO
            {
                return new SendResultDTO(true, 'msg_' . rand(), true);
            }
        });

        $response = $this->postJson('/api/v1/notifications/batch', [
            'channel'       => 'sms',
            'priority'      => 'transactional',
            'message_text'  => 'Hello',
            'recipient_ids' => $recipients->pluck('id')->all(),
        ]);

        $response->assertStatus(202);
        $batchId = $response->json('data.batch_id');

        Notification::where('batch_id', $batchId)->each(function (Notification $n) {
            ProcessNotificationJob::dispatchSync($n->id);
        });

        $batch = NotificationBatch::find($batchId);
        $this->assertEquals(3, $batch->total_count);
        $this->assertEquals(0, $batch->queued_count);
        $this->assertEquals(3, $batch->delivered_count);
    }
}

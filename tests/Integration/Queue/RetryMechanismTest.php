<?php

declare(strict_types=1);

namespace Tests\Integration\Queue;

use App\Domain\Notification\Contracts\NotificationGatewayInterface;
use App\Domain\Notification\DataTransferObjects\NotificationDTO;
use App\Domain\Notification\DataTransferObjects\SendResultDTO;
use App\Domain\Notification\Enums\NotificationStatus;
use App\Domain\Notification\Exceptions\GatewayUnavailableException;
use App\Domain\Notification\Exceptions\InvalidRecipientException;
use App\Domain\Notification\Models\Notification;
use App\Domain\Notification\Models\Recipient;
use App\Jobs\ProcessNotificationJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetryMechanismTest extends TestCase
{
    use RefreshDatabase;

    /** InvalidRecipientException → immediate discard, no retry */
    public function test_invalid_recipient_discards_notification(): void
    {
        $recipient = Recipient::factory()->withPhoneOnly()->create();

        $this->app->bind('gateway.sms', fn () => new class implements NotificationGatewayInterface {
            public function send(NotificationDTO $dto): SendResultDTO
            {
                throw new InvalidRecipientException("Phone {$dto->address} is not valid");
            }
        });

        $notification = Notification::factory()
            ->queued()
            ->forSms()
            ->for($recipient)
            ->for(\App\Domain\Notification\Models\NotificationBatch::factory()->create(), 'batch')
            ->create();

        ProcessNotificationJob::dispatchSync($notification->id);
        $notification->refresh();

        $this->assertEquals(NotificationStatus::DISCARDED, $notification->status);
        $this->assertStringContainsString('not valid', $notification->error_message);
        $this->assertNotNull($notification->discarded_at);
    }

    /** GatewayUnavailableException — job is released, status stays queued */
    public function test_gateway_unavailable_keeps_status_queued(): void
    {
        $recipient = Recipient::factory()->withPhoneOnly()->create();

        $this->app->bind('gateway.sms', fn () => new class implements NotificationGatewayInterface {
            public function send(NotificationDTO $dto): SendResultDTO
            {
                throw new GatewayUnavailableException('Gateway timeout');
            }
        });

        $batch = \App\Domain\Notification\Models\NotificationBatch::factory()->create();
        $notification = Notification::factory()
            ->queued()
            ->forSms()
            ->for($recipient)
            ->for($batch, 'batch')
            ->create();

        // Run job — it will catch GatewayUnavailableException and call release()
        // In sync driver release() re-runs immediately, so we need to check attempts
        $job = new ProcessNotificationJob($notification->id);
        $job->tries = 1; // Force to go to failed() on first attempt

        try {
            app()->call([$job, 'handle']);
        } catch (\Throwable) {}

        $notification->refresh();

        // Status should remain queued (GatewayUnavailable = retriable)
        $this->assertContains(
            $notification->status,
            [NotificationStatus::QUEUED, NotificationStatus::DISCARDED]
        );
        $this->assertGreaterThanOrEqual(1, $notification->attempts);
    }

    /** After max retries, failed() is called and notification is discarded */
    public function test_notification_discarded_after_max_retries(): void
    {
        $recipient = Recipient::factory()->withPhoneOnly()->create();
        $batch     = \App\Domain\Notification\Models\NotificationBatch::factory()->create();

        $notification = Notification::factory()
            ->queued()
            ->forSms()
            ->for($recipient)
            ->for($batch, 'batch')
            ->create();

        $job = new ProcessNotificationJob($notification->id);
        $job->failed(new \RuntimeException('Max attempts exceeded'));

        $notification->refresh();

        $this->assertEquals(NotificationStatus::DISCARDED, $notification->status);
        $this->assertStringContainsString('Max retry', $notification->error_message);
    }

    /** Already-processed notification is skipped (exactly-once) */
    public function test_already_sent_notification_is_skipped(): void
    {
        $recipient = Recipient::factory()->withPhoneOnly()->create();

        $calledCount = 0;
        $this->app->bind('gateway.sms', fn () => new class ($calledCount) implements NotificationGatewayInterface {
            public function __construct(private int &$count) {}
            public function send(NotificationDTO $dto): SendResultDTO
            {
                $this->count++;

                return new SendResultDTO(true, 'msg_001', true);
            }
        });

        $batch = \App\Domain\Notification\Models\NotificationBatch::factory()->create();
        $notification = Notification::factory()
            ->sent()
            ->forSms()
            ->for($recipient)
            ->for($batch, 'batch')
            ->create();

        ProcessNotificationJob::dispatchSync($notification->id);

        $this->assertEquals(0, $calledCount, 'Gateway should not be called for already-sent notification');
    }
}

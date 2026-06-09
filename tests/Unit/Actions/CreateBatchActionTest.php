<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Domain\Notification\Actions\CreateBatchAction;
use App\Domain\Notification\DataTransferObjects\CreateBatchDTO;
use App\Domain\Notification\Enums\NotificationChannel;
use App\Domain\Notification\Enums\NotificationPriority;
use App\Domain\Notification\Models\Notification;
use App\Domain\Notification\Models\Recipient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CreateBatchActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_batch_and_dispatches_jobs(): void
    {
        Queue::fake();

        $recipients = Recipient::factory(3)->create();

        $dto = new CreateBatchDTO(
            channel:        NotificationChannel::SMS,
            priority:       NotificationPriority::TRANSACTIONAL,
            messageText:    'Test message',
            recipientIds:   $recipients->pluck('id')->all(),
        );

        $action = app(CreateBatchAction::class);
        [$batch, $isNew] = $action->execute($dto);

        $this->assertTrue($isNew);
        $this->assertEquals(3, $batch->total_count);
        $this->assertEquals(3, $batch->queued_count);

        $this->assertDatabaseCount('notifications', 3);

        Queue::assertPushedOn(
            NotificationPriority::TRANSACTIONAL->queueName(),
            \App\Jobs\ProcessNotificationJob::class
        );
    }

    public function test_returns_existing_batch_on_duplicate_payload(): void
    {
        Queue::fake();

        $recipients = Recipient::factory(2)->create();
        $ids        = $recipients->pluck('id')->sort()->values()->all();

        $dto = new CreateBatchDTO(
            channel:      NotificationChannel::EMAIL,
            priority:     NotificationPriority::MARKETING,
            messageText:  'Sale!',
            recipientIds: $ids,
        );

        $action = app(CreateBatchAction::class);

        [$batch1, $isNew1] = $action->execute($dto);
        [$batch2, $isNew2] = $action->execute($dto);

        $this->assertTrue($isNew1);
        $this->assertFalse($isNew2);
        $this->assertEquals($batch1->id, $batch2->id);

        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_excludes_inactive_recipients(): void
    {
        Queue::fake();

        $active   = Recipient::factory()->create(['is_active' => true]);
        $inactive = Recipient::factory()->create(['is_active' => false]);

        $dto = new CreateBatchDTO(
            channel:      NotificationChannel::SMS,
            priority:     NotificationPriority::MARKETING,
            messageText:  'Hello',
            recipientIds: [$active->id, $inactive->id],
        );

        $action = app(CreateBatchAction::class);
        [$batch] = $action->execute($dto);

        $this->assertEquals(1, $batch->total_count);
        $this->assertDatabaseHas('notifications', ['recipient_id' => $active->id]);
        $this->assertDatabaseMissing('notifications', ['recipient_id' => $inactive->id]);
    }

    public function test_marketing_notifications_dispatched_to_marketing_queue(): void
    {
        Queue::fake();

        $recipient = Recipient::factory()->create();

        $dto = new CreateBatchDTO(
            channel:      NotificationChannel::EMAIL,
            priority:     NotificationPriority::MARKETING,
            messageText:  'Promo',
            recipientIds: [$recipient->id],
        );

        app(CreateBatchAction::class)->execute($dto);

        Queue::assertPushedOn('notifications.marketing', \App\Jobs\ProcessNotificationJob::class);
    }
}

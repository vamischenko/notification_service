<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Notification\Actions\ProcessNotificationAction;
use App\Domain\Notification\Enums\NotificationStatus;
use App\Domain\Notification\Exceptions\GatewayUnavailableException;
use App\Domain\Notification\Exceptions\InvalidRecipientException;
use App\Domain\Notification\Models\Notification;
use App\Domain\Notification\Services\DeduplicationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

class ProcessNotificationJob implements ShouldQueue
{
    use Queueable;
    use InteractsWithQueue;
    use SerializesModels;

    public int $tries   = 5;
    public int $timeout = 30;

    public function __construct(
        public readonly string $notificationId,
    ) {}

    /** Exponential backoff: 1m, 5m, 15m, 30m, 60m */
    public function backoff(): array
    {
        return [60, 300, 900, 1800, 3600];
    }

    public function handle(
        ProcessNotificationAction $action,
        DeduplicationService      $dedup,
    ): void {
        $workerId = Str::uuid()->toString();

        // Exactly-once: acquire Redis lock before processing
        if (! $dedup->acquireLock($this->notificationId, $workerId)) {
            return;
        }

        try {
            $notification = DB::transaction(function (): ?Notification {
                $notification = Notification::lockForUpdate()->find($this->notificationId);

                if ($notification === null || $notification->status !== NotificationStatus::QUEUED) {
                    return null;
                }

                $notification->increment('attempts');
                $notification->last_attempt_at = now();
                $notification->save();

                return $notification->fresh();
            });

            if ($notification === null) {
                return;
            }

            $result = $action->execute($notification);

            DB::transaction(function () use ($notification, $result): void {
                $locked = Notification::lockForUpdate()->find($this->notificationId);

                if ($locked === null || $locked->status !== NotificationStatus::QUEUED) {
                    return;
                }

                $locked->transitionTo(NotificationStatus::SENT);
                $locked->provider_message_id = $result->providerMessageId;
                $locked->save();

                $this->updateBatchCounter($locked, 'sent_count');

                if ($result->isDelivered) {
                    $locked->transitionTo(NotificationStatus::DELIVERED);
                    $locked->save();
                    $this->updateBatchCounter($locked, 'delivered_count');
                }
            });
        } catch (GatewayUnavailableException $e) {
            $dedup->releaseLock($this->notificationId, $workerId);

            $delay = $this->backoff()[min($this->attempts() - 1, 4)];
            $this->release($delay);

            return;
        } catch (InvalidRecipientException $e) {
            $this->discardNotification($this->notificationId, $e->getMessage());
            $this->delete();

            return;
        } finally {
            $dedup->releaseLock($this->notificationId, $workerId);
        }
    }

    public function failed(Throwable $e): void
    {
        $this->discardNotification(
            $this->notificationId,
            'Max retry attempts exceeded: ' . $e->getMessage()
        );
    }

    private function discardNotification(string $id, string $reason): void
    {
        $notification = Notification::find($id);

        if ($notification === null || ! $notification->status->canTransitionTo(NotificationStatus::DISCARDED)) {
            return;
        }

        $notification->transitionTo(NotificationStatus::DISCARDED);
        $notification->error_message = $reason;
        $notification->save();

        $this->updateBatchCounter($notification, 'discarded_count');
    }

    private function updateBatchCounter(Notification $notification, string $column): void
    {
        $notification->batch()->increment($column);

        if (in_array($column, ['sent_count', 'discarded_count'])) {
            $notification->batch()->decrement('queued_count');
        }
    }
}

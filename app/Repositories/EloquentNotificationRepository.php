<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Domain\Notification\Contracts\NotificationRepositoryInterface;
use App\Domain\Notification\DataTransferObjects\CreateBatchDTO;
use App\Domain\Notification\Models\Notification;
use App\Domain\Notification\Models\NotificationBatch;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class EloquentNotificationRepository implements NotificationRepositoryInterface
{
    public function findOrCreateBatch(CreateBatchDTO $dto, string $fingerprint): array
    {
        // ON CONFLICT DO UPDATE with xmax trick: xmax=0 means the row was just inserted
        $result = DB::selectOne(
            'INSERT INTO notification_batches
                (id, channel, priority, message_text, idempotency_key, created_at, updated_at)
             VALUES
                (gen_random_uuid(), ?, ?, ?, ?, NOW(), NOW())
             ON CONFLICT (idempotency_key) DO UPDATE
                SET updated_at = notification_batches.updated_at
             RETURNING id, (xmax = 0) AS inserted',
            [
                $dto->channel->value,
                $dto->priority->value,
                $dto->messageText,
                $fingerprint,
            ],
        );

        $batch = NotificationBatch::findOrFail($result->id);
        $isNew = (bool) $result->inserted;

        return [$batch, $isNew];
    }

    public function bulkInsertNotifications(array $rows): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            // insertOrIgnore handles duplicate idempotency_key gracefully
            DB::table('notifications')->insertOrIgnore($chunk);
        }
    }

    public function findById(string $id): ?Notification
    {
        return Notification::find($id);
    }

    public function getRecipientNotifications(
        string $recipientId,
        array  $filters,
        int    $perPage,
    ): LengthAwarePaginator {
        $query = Notification::query()
            ->where('recipient_id', $recipientId)
            ->orderBy('created_at', 'desc');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }

        return $query->paginate($perPage);
    }
}

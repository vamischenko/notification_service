<?php

declare(strict_types=1);

namespace App\Domain\Notification\Actions;

use App\Domain\Notification\Contracts\NotificationRepositoryInterface;
use App\Domain\Notification\Contracts\RecipientRepositoryInterface;
use App\Domain\Notification\DataTransferObjects\CreateBatchDTO;
use App\Domain\Notification\Models\NotificationBatch;
use App\Jobs\ProcessNotificationJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateBatchAction
{
    public function __construct(
        private readonly NotificationRepositoryInterface $notificationRepo,
        private readonly RecipientRepositoryInterface    $recipientRepo,
    ) {}

    /** @return array{0: NotificationBatch, 1: bool} [batch, isNew] */
    public function execute(CreateBatchDTO $dto): array
    {
        $fingerprint = $this->buildFingerprint($dto);

        [$batch, $isNew] = $this->notificationRepo->findOrCreateBatch($dto, $fingerprint);

        if (! $isNew) {
            return [$batch, false];
        }

        $recipients = $this->recipientRepo->findActiveByIds($dto->recipientIds);
        $validIds   = $recipients->pluck('id')->all();

        if (empty($validIds)) {
            return [$batch, true];
        }

        $now   = now()->toDateTimeString();
        $rows  = array_map(fn (string $recipientId) => [
            'id'              => Str::uuid()->toString(),
            'batch_id'        => $batch->id,
            'recipient_id'    => $recipientId,
            'channel'         => $dto->channel->value,
            'priority'        => $dto->priority->value,
            'message_text'    => $dto->messageText,
            'status'          => 'queued',
            'idempotency_key' => hash('sha256', $batch->id . $recipientId),
            'attempts'        => 0,
            'created_at'      => $now,
            'updated_at'      => $now,
        ], $validIds);

        DB::transaction(function () use ($batch, $rows, $validIds) {
            $this->notificationRepo->bulkInsertNotifications($rows);

            $batch->update([
                'total_count'  => count($validIds),
                'queued_count' => count($validIds),
            ]);
        });

        // Dispatch jobs per notification into priority queue
        $queue = $dto->priority->queueName();

        foreach ($rows as $row) {
            ProcessNotificationJob::dispatch($row['id'])->onQueue($queue);
        }

        return [$batch->refresh(), true];
    }

    private function buildFingerprint(CreateBatchDTO $dto): string
    {
        $ids = $dto->recipientIds;
        sort($ids);

        return hash('sha256',
            $dto->channel->value
            . $dto->priority->value
            . $dto->messageText
            . implode(',', $ids)
        );
    }
}

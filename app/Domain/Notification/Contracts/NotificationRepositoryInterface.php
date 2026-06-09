<?php

declare(strict_types=1);

namespace App\Domain\Notification\Contracts;

use App\Domain\Notification\DataTransferObjects\CreateBatchDTO;
use App\Domain\Notification\Models\Notification;
use App\Domain\Notification\Models\NotificationBatch;
use Illuminate\Pagination\LengthAwarePaginator;

interface NotificationRepositoryInterface
{
    /** @return array{0: NotificationBatch, 1: bool} [batch, isNew] */
    public function findOrCreateBatch(CreateBatchDTO $dto, string $fingerprint): array;

    public function bulkInsertNotifications(array $rows): void;

    public function findById(string $id): ?Notification;

    public function getRecipientNotifications(
        string $recipientId,
        array  $filters,
        int    $perPage,
    ): LengthAwarePaginator;
}

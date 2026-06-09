<?php

declare(strict_types=1);

namespace App\Domain\Notification\DataTransferObjects;

use App\Domain\Notification\Enums\NotificationChannel;
use App\Domain\Notification\Enums\NotificationPriority;

readonly class CreateBatchDTO
{
    public function __construct(
        public NotificationChannel  $channel,
        public NotificationPriority $priority,
        public string               $messageText,
        public array                $recipientIds,
        public ?string              $idempotencyKey = null,
    ) {}
}

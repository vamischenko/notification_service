<?php

declare(strict_types=1);

namespace App\Domain\Notification\DataTransferObjects;

use App\Domain\Notification\Enums\NotificationChannel;
use App\Domain\Notification\Enums\NotificationPriority;
use App\Domain\Notification\Models\Notification;

readonly class NotificationDTO
{
    public function __construct(
        public string               $notificationId,
        public string               $recipientId,
        public string               $address,
        public NotificationChannel  $channel,
        public NotificationPriority $priority,
        public string               $messageText,
    ) {}

    public static function fromModel(Notification $notification): self
    {
        $recipient = $notification->recipient;
        $address   = $notification->channel === NotificationChannel::SMS
            ? (string) $recipient->phone
            : (string) $recipient->email;

        return new self(
            notificationId: $notification->id,
            recipientId:    $notification->recipient_id,
            address:        $address,
            channel:        $notification->channel,
            priority:       $notification->priority,
            messageText:    $notification->message_text,
        );
    }
}

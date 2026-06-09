<?php

declare(strict_types=1);

namespace App\Domain\Notification\Enums;

enum NotificationPriority: string
{
    case TRANSACTIONAL = 'transactional';
    case MARKETING     = 'marketing';

    public function queueName(): string
    {
        return match ($this) {
            self::TRANSACTIONAL => 'notifications.transactional',
            self::MARKETING     => 'notifications.marketing',
        };
    }

    public function routingKey(NotificationChannel $channel): string
    {
        return "notification.{$this->value}.{$channel->value}";
    }
}

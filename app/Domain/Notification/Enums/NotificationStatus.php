<?php

declare(strict_types=1);

namespace App\Domain\Notification\Enums;

enum NotificationStatus: string
{
    case QUEUED    = 'queued';
    case SENT      = 'sent';
    case DELIVERED = 'delivered';
    case DISCARDED = 'discarded';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::QUEUED    => in_array($next, [self::SENT, self::DISCARDED]),
            self::SENT      => in_array($next, [self::DELIVERED, self::DISCARDED]),
            self::DELIVERED => false,
            self::DISCARDED => false,
        };
    }
}

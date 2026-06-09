<?php

declare(strict_types=1);

namespace App\Domain\Notification\Enums;

enum NotificationChannel: string
{
    case SMS   = 'sms';
    case EMAIL = 'email';
}

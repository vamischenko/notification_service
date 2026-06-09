<?php

declare(strict_types=1);

namespace App\Domain\Notification\Actions;

use App\Domain\Notification\Contracts\NotificationGatewayInterface;
use App\Domain\Notification\DataTransferObjects\NotificationDTO;
use App\Domain\Notification\DataTransferObjects\SendResultDTO;
use App\Domain\Notification\Enums\NotificationChannel;
use App\Domain\Notification\Models\Notification;

class ProcessNotificationAction
{
    public function __construct(
        private readonly NotificationGatewayInterface $smsGateway,
        private readonly NotificationGatewayInterface $emailGateway,
    ) {}

    public function execute(Notification $notification): SendResultDTO
    {
        $gateway = match ($notification->channel) {
            NotificationChannel::SMS   => $this->smsGateway,
            NotificationChannel::EMAIL => $this->emailGateway,
        };

        return $gateway->send(NotificationDTO::fromModel($notification));
    }
}

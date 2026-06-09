<?php

declare(strict_types=1);

namespace App\Domain\Notification\Contracts;

use App\Domain\Notification\DataTransferObjects\NotificationDTO;
use App\Domain\Notification\DataTransferObjects\SendResultDTO;
use App\Domain\Notification\Exceptions\GatewayUnavailableException;
use App\Domain\Notification\Exceptions\InvalidRecipientException;

interface NotificationGatewayInterface
{
    /**
     * @throws GatewayUnavailableException — temporary failure, retry allowed
     * @throws InvalidRecipientException   — permanent failure, discard
     */
    public function send(NotificationDTO $dto): SendResultDTO;
}

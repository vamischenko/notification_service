<?php

declare(strict_types=1);

namespace App\Gateways\Sms;

use App\Domain\Notification\Contracts\NotificationGatewayInterface;
use App\Domain\Notification\DataTransferObjects\NotificationDTO;
use App\Domain\Notification\DataTransferObjects\SendResultDTO;
use App\Domain\Notification\Exceptions\GatewayUnavailableException;
use App\Domain\Notification\Exceptions\InvalidRecipientException;
use Illuminate\Support\Str;

class MockSmsGateway implements NotificationGatewayInterface
{
    public function send(NotificationDTO $dto): SendResultDTO
    {
        $roll = random_int(1, 100);

        if ($roll <= 5) {
            throw new GatewayUnavailableException("SMS gateway timeout (simulated)");
        }

        if ($roll <= 8) {
            throw new InvalidRecipientException("Invalid phone number: {$dto->address}");
        }

        return new SendResultDTO(
            success:           true,
            providerMessageId: 'sms_mock_' . Str::random(12),
            isDelivered:       $roll > 20,
        );
    }
}

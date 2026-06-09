<?php

declare(strict_types=1);

namespace App\Gateways\Email;

use App\Domain\Notification\Contracts\NotificationGatewayInterface;
use App\Domain\Notification\DataTransferObjects\NotificationDTO;
use App\Domain\Notification\DataTransferObjects\SendResultDTO;
use App\Domain\Notification\Exceptions\GatewayUnavailableException;
use App\Domain\Notification\Exceptions\InvalidRecipientException;
use Illuminate\Support\Str;

class MockEmailGateway implements NotificationGatewayInterface
{
    public function send(NotificationDTO $dto): SendResultDTO
    {
        $roll = random_int(1, 100);

        if ($roll <= 3) {
            throw new GatewayUnavailableException("Email gateway unavailable (simulated)");
        }

        if ($roll <= 5) {
            throw new InvalidRecipientException("Invalid email address: {$dto->address}");
        }

        return new SendResultDTO(
            success:           true,
            providerMessageId: 'email_mock_' . Str::random(12),
            isDelivered:       $roll > 15,
        );
    }
}

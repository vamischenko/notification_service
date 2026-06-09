<?php

declare(strict_types=1);

namespace App\Domain\Notification\DataTransferObjects;

readonly class SendResultDTO
{
    public function __construct(
        public bool    $success,
        public string  $providerMessageId,
        public bool    $isDelivered = false,
        public ?string $errorMessage = null,
    ) {}
}

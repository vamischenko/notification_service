<?php

declare(strict_types=1);

namespace Tests\Unit\Gateways;

use App\Domain\Notification\DataTransferObjects\NotificationDTO;
use App\Domain\Notification\Enums\NotificationChannel;
use App\Domain\Notification\Enums\NotificationPriority;
use App\Domain\Notification\Exceptions\GatewayUnavailableException;
use App\Domain\Notification\Exceptions\InvalidRecipientException;
use App\Gateways\Email\MockEmailGateway;
use App\Gateways\Sms\MockSmsGateway;
use Tests\TestCase;

class MockGatewayTest extends TestCase
{
    private NotificationDTO $dto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dto = new NotificationDTO(
            notificationId: 'test-id',
            recipientId:    'recipient-id',
            address:        '+79991234567',
            channel:        NotificationChannel::SMS,
            priority:       NotificationPriority::TRANSACTIONAL,
            messageText:    'Test message',
        );
    }

    public function test_sms_gateway_implements_interface(): void
    {
        $gateway = new MockSmsGateway();

        $this->assertInstanceOf(
            \App\Domain\Notification\Contracts\NotificationGatewayInterface::class,
            $gateway
        );
    }

    public function test_sms_gateway_returns_send_result_on_success(): void
    {
        $gateway = new MockSmsGateway();

        $successes = 0;
        $attempts  = 50;

        for ($i = 0; $i < $attempts; $i++) {
            try {
                $result = $gateway->send($this->dto);
                $successes++;
                $this->assertTrue($result->success);
                $this->assertNotEmpty($result->providerMessageId);
            } catch (GatewayUnavailableException|InvalidRecipientException) {
                // Expected in some cases
            }
        }

        $this->assertGreaterThan(30, $successes, 'Expected majority of calls to succeed');
    }

    public function test_email_gateway_returns_send_result_on_success(): void
    {
        $dto = new NotificationDTO(
            notificationId: 'test-id',
            recipientId:    'recipient-id',
            address:        'test@example.com',
            channel:        NotificationChannel::EMAIL,
            priority:       NotificationPriority::MARKETING,
            messageText:    'Test email',
        );

        $gateway  = new MockEmailGateway();
        $successes = 0;

        for ($i = 0; $i < 50; $i++) {
            try {
                $result = $gateway->send($dto);
                $successes++;
                $this->assertTrue($result->success);
            } catch (GatewayUnavailableException|InvalidRecipientException) {}
        }

        $this->assertGreaterThan(30, $successes);
    }
}

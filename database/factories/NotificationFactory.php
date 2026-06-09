<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Notification\Enums\NotificationChannel;
use App\Domain\Notification\Enums\NotificationPriority;
use App\Domain\Notification\Enums\NotificationStatus;
use App\Domain\Notification\Models\Notification;
use App\Domain\Notification\Models\NotificationBatch;
use App\Domain\Notification\Models\Recipient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'batch_id'     => NotificationBatch::factory(),
            'recipient_id' => Recipient::factory(),
            'channel'      => $this->faker->randomElement(NotificationChannel::cases())->value,
            'priority'     => $this->faker->randomElement(NotificationPriority::cases())->value,
            'message_text' => $this->faker->sentence(),
            'status'       => NotificationStatus::QUEUED->value,
            'idempotency_key' => hash('sha256', Str::uuid()->toString()),
            'attempts'     => 0,
        ];
    }

    public function queued(): static
    {
        return $this->state(['status' => NotificationStatus::QUEUED->value]);
    }

    public function sent(): static
    {
        return $this->state([
            'status'  => NotificationStatus::SENT->value,
            'sent_at' => now(),
        ]);
    }

    public function delivered(): static
    {
        return $this->state([
            'status'       => NotificationStatus::DELIVERED->value,
            'sent_at'      => now()->subSeconds(5),
            'delivered_at' => now(),
        ]);
    }

    public function discarded(): static
    {
        return $this->state([
            'status'        => NotificationStatus::DISCARDED->value,
            'discarded_at'  => now(),
            'error_message' => 'Gateway error',
        ]);
    }

    public function forSms(): static
    {
        return $this->state(['channel' => NotificationChannel::SMS->value]);
    }

    public function forEmail(): static
    {
        return $this->state(['channel' => NotificationChannel::EMAIL->value]);
    }

    public function transactional(): static
    {
        return $this->state(['priority' => NotificationPriority::TRANSACTIONAL->value]);
    }
}

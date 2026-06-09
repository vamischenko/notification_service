<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Notification\Enums\NotificationChannel;
use App\Domain\Notification\Enums\NotificationPriority;
use App\Domain\Notification\Models\NotificationBatch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class NotificationBatchFactory extends Factory
{
    protected $model = NotificationBatch::class;

    public function definition(): array
    {
        return [
            'channel'         => $this->faker->randomElement(NotificationChannel::cases())->value,
            'priority'        => $this->faker->randomElement(NotificationPriority::cases())->value,
            'message_text'    => $this->faker->sentence(),
            'total_count'     => 0,
            'queued_count'    => 0,
            'sent_count'      => 0,
            'delivered_count' => 0,
            'discarded_count' => 0,
            'idempotency_key' => hash('sha256', Str::random(32)),
        ];
    }

    public function transactional(): static
    {
        return $this->state(['priority' => NotificationPriority::TRANSACTIONAL->value]);
    }

    public function marketing(): static
    {
        return $this->state(['priority' => NotificationPriority::MARKETING->value]);
    }
}

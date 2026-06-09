<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Notification\Models\Recipient;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecipientFactory extends Factory
{
    protected $model = Recipient::class;

    public function definition(): array
    {
        return [
            'name'      => $this->faker->name(),
            'email'     => $this->faker->unique()->safeEmail(),
            'phone'     => '+7' . $this->faker->numerify('##########'),
            'is_active' => true,
        ];
    }

    public function withEmailOnly(): static
    {
        return $this->state(['phone' => null]);
    }

    public function withPhoneOnly(): static
    {
        return $this->state(['email' => null]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}

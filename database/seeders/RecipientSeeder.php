<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Notification\Models\Recipient;
use Illuminate\Database\Seeder;

class RecipientSeeder extends Seeder
{
    public function run(): void
    {
        // Recipients with both email and phone
        Recipient::factory(20)->create();

        // Email-only recipients
        Recipient::factory(5)->withEmailOnly()->create();

        // Phone-only recipients
        Recipient::factory(5)->withPhoneOnly()->create();

        // Inactive recipients
        Recipient::factory(3)->inactive()->create();

        $this->command->info('Created 33 recipients (20 full, 5 email-only, 5 phone-only, 3 inactive)');
    }
}

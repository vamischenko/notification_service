<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Domain\Notification\Models\Recipient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_batch_returns_202_with_correct_structure(): void
    {
        Queue::fake();

        $recipients = Recipient::factory(2)->create();

        $response = $this->postJson('/api/v1/notifications/batch', [
            'channel'       => 'sms',
            'priority'      => 'transactional',
            'message_text'  => 'Hello',
            'recipient_ids' => $recipients->pluck('id')->all(),
        ]);

        $response->assertStatus(202)
            ->assertJsonStructure([
                'data' => [
                    'batch_id',
                    'channel',
                    'priority',
                    'message_text',
                    'total_count',
                    'queued_count',
                    'sent_count',
                    'delivered_count',
                    'discarded_count',
                    'progress_percent',
                    'created_at',
                ],
                'meta' => ['idempotent'],
            ]);

        $response->assertJsonPath('data.channel', 'sms')
            ->assertJsonPath('data.priority', 'transactional')
            ->assertJsonPath('data.total_count', 2)
            ->assertJsonPath('data.queued_count', 2)
            ->assertJsonPath('meta.idempotent', false);
    }

    public function test_validation_fails_for_invalid_channel(): void
    {
        $response = $this->postJson('/api/v1/notifications/batch', [
            'channel'       => 'telegram',
            'priority'      => 'transactional',
            'message_text'  => 'Hello',
            'recipient_ids' => [fake()->uuid()],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['channel']);
    }

    public function test_validation_fails_for_invalid_priority(): void
    {
        $response = $this->postJson('/api/v1/notifications/batch', [
            'channel'       => 'sms',
            'priority'      => 'urgent',
            'message_text'  => 'Hello',
            'recipient_ids' => [fake()->uuid()],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['priority']);
    }

    public function test_validation_fails_for_missing_recipients(): void
    {
        $response = $this->postJson('/api/v1/notifications/batch', [
            'channel'      => 'email',
            'priority'     => 'marketing',
            'message_text' => 'Hello',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['recipient_ids']);
    }

    public function test_validation_fails_for_message_too_long(): void
    {
        $response = $this->postJson('/api/v1/notifications/batch', [
            'channel'       => 'email',
            'priority'      => 'marketing',
            'message_text'  => str_repeat('a', 1001),
            'recipient_ids' => [fake()->uuid()],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['message_text']);
    }

    public function test_inactive_recipients_are_excluded(): void
    {
        $active   = Recipient::factory()->create(['is_active' => true]);
        $inactive = Recipient::factory()->create(['is_active' => false]);

        $response = $this->postJson('/api/v1/notifications/batch', [
            'channel'       => 'sms',
            'priority'      => 'marketing',
            'message_text'  => 'Promo',
            'recipient_ids' => [$active->id, $inactive->id],
        ]);

        $response->assertStatus(202);
        $response->assertJsonPath('data.total_count', 1);

        $this->assertDatabaseHas('notifications', ['recipient_id' => $active->id]);
        $this->assertDatabaseMissing('notifications', ['recipient_id' => $inactive->id]);
    }

    public function test_get_batch_status_returns_correct_data(): void
    {
        $recipients = Recipient::factory(1)->create();

        $create = $this->postJson('/api/v1/notifications/batch', [
            'channel'       => 'email',
            'priority'      => 'transactional',
            'message_text'  => 'Status test',
            'recipient_ids' => $recipients->pluck('id')->all(),
        ]);

        $batchId = $create->json('data.batch_id');

        $response = $this->getJson("/api/v1/notifications/batches/{$batchId}");

        $response->assertStatus(200)
            ->assertJsonPath('data.batch_id', $batchId)
            ->assertJsonStructure(['data' => ['batch_id', 'total_count', 'queued_count']]);
    }
}

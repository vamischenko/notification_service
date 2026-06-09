<?php

declare(strict_types=1);

namespace Tests\Integration\Deduplication;

use App\Domain\Notification\Models\Recipient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::flushdb();
    }

    /** Same payload twice → one batch, no duplicate notifications */
    public function test_same_payload_creates_one_batch(): void
    {
        $recipients = Recipient::factory(3)->create();

        $payload = [
            'channel'       => 'sms',
            'priority'      => 'marketing',
            'message_text'  => 'Duplicate test message',
            'recipient_ids' => $recipients->pluck('id')->sort()->values()->all(),
        ];

        $first  = $this->postJson('/api/v1/notifications/batch', $payload);
        $second = $this->postJson('/api/v1/notifications/batch', $payload);

        $first->assertStatus(202);
        $second->assertStatus(200);
        $second->assertJsonPath('meta.idempotent', false);

        $this->assertEquals(
            $first->json('data.batch_id'),
            $second->json('data.batch_id')
        );

        $this->assertDatabaseCount('notifications', 3);
    }

    /** HTTP Idempotency-Key header deduplication */
    public function test_idempotency_key_header_returns_cached_response(): void
    {
        $recipient = Recipient::factory()->create();
        $key       = Str::uuid()->toString();

        $payload = [
            'channel'       => 'email',
            'priority'      => 'transactional',
            'message_text'  => 'Code: 1234',
            'recipient_ids' => [$recipient->id],
        ];

        $first = $this->postJson('/api/v1/notifications/batch', $payload, [
            'Idempotency-Key' => $key,
        ]);
        $first->assertStatus(202);

        $second = $this->postJson('/api/v1/notifications/batch', $payload, [
            'Idempotency-Key' => $key,
        ]);
        $second->assertStatus(200);
        $second->assertJsonPath('meta.idempotent', true);

        $this->assertEquals(
            $first->json('data.batch_id'),
            $second->json('data.batch_id')
        );

        // Only one batch and one notification created
        $this->assertDatabaseCount('notification_batches', 1);
        $this->assertDatabaseCount('notifications', 1);
    }

    /** Different Idempotency-Key with same payload → separate batches */
    public function test_different_idempotency_keys_create_separate_batches(): void
    {
        $recipient = Recipient::factory()->create();

        $payload = [
            'channel'       => 'sms',
            'priority'      => 'transactional',
            'message_text'  => 'Unique per key',
            'recipient_ids' => [$recipient->id],
        ];

        $first = $this->postJson('/api/v1/notifications/batch', $payload, [
            'Idempotency-Key' => Str::uuid()->toString(),
        ]);

        $second = $this->postJson('/api/v1/notifications/batch', $payload, [
            'Idempotency-Key' => Str::uuid()->toString(),
        ]);

        $first->assertStatus(202);
        $second->assertStatus(200); // Same business fingerprint → same batch

        $this->assertEquals(
            $first->json('data.batch_id'),
            $second->json('data.batch_id')
        );
    }
}

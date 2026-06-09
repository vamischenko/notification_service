<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Domain\Notification\Models\Notification;
use App\Domain\Notification\Models\NotificationBatch;
use App\Domain\Notification\Models\Recipient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_recipient_notifications_returns_paginated_list(): void
    {
        $recipient = Recipient::factory()->create();
        $batch     = NotificationBatch::factory()->create();

        Notification::factory(5)->forSms()->for($recipient)->for($batch, 'batch')->create();

        $response = $this->getJson("/api/v1/recipients/{$recipient->id}/notifications");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'batch_id', 'channel', 'priority',
                        'message_text', 'status', 'attempts', 'created_at',
                    ],
                ],
                'meta' => ['current_page', 'total', 'per_page'],
                'links',
            ]);

        $this->assertCount(5, $response->json('data'));
    }

    public function test_filter_notifications_by_status(): void
    {
        $recipient = Recipient::factory()->create();
        $batch     = NotificationBatch::factory()->create();

        Notification::factory(3)->delivered()->for($recipient)->for($batch, 'batch')->create();
        Notification::factory(2)->queued()->for($recipient)->for($batch, 'batch')->create();

        $response = $this->getJson("/api/v1/recipients/{$recipient->id}/notifications?status=delivered");

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));

        foreach ($response->json('data') as $item) {
            $this->assertEquals('delivered', $item['status']);
        }
    }

    public function test_filter_notifications_by_channel(): void
    {
        $recipient = Recipient::factory()->create();
        $batch     = NotificationBatch::factory()->create();

        Notification::factory(2)->forSms()->for($recipient)->for($batch, 'batch')->create();
        Notification::factory(3)->forEmail()->for($recipient)->for($batch, 'batch')->create();

        $response = $this->getJson("/api/v1/recipients/{$recipient->id}/notifications?channel=email");

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_returns_404_for_unknown_recipient(): void
    {
        $response = $this->getJson('/api/v1/recipients/00000000-0000-0000-0000-000000000000/notifications');

        $response->assertStatus(404);
    }

    public function test_pagination_works_correctly(): void
    {
        $recipient = Recipient::factory()->create();
        $batch     = NotificationBatch::factory()->create();

        Notification::factory(25)->for($recipient)->for($batch, 'batch')->create();

        $response = $this->getJson("/api/v1/recipients/{$recipient->id}/notifications?per_page=10&page=1");

        $response->assertStatus(200);
        $this->assertCount(10, $response->json('data'));
        $this->assertEquals(25, $response->json('meta.total'));
        $this->assertEquals(3, $response->json('meta.last_page'));
    }

    public function test_notifications_ordered_by_created_at_desc(): void
    {
        $recipient = Recipient::factory()->create();
        $batch     = NotificationBatch::factory()->create();

        $old = Notification::factory()->for($recipient)->for($batch, 'batch')->create([
            'created_at' => now()->subHour(),
        ]);
        $new = Notification::factory()->for($recipient)->for($batch, 'batch')->create([
            'created_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/recipients/{$recipient->id}/notifications");

        $data = $response->json('data');
        $this->assertEquals($new->id, $data[0]['id']);
        $this->assertEquals($old->id, $data[1]['id']);
    }
}

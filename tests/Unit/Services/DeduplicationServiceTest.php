<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Domain\Notification\Services\DeduplicationService;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class DeduplicationServiceTest extends TestCase
{
    private DeduplicationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DeduplicationService();
    }

    public function test_acquire_lock_returns_true_when_key_not_exists(): void
    {
        $notificationId = 'test-notification-1';
        $workerId       = 'worker-1';

        Redis::del('notif:processing:' . $notificationId);

        $result = $this->service->acquireLock($notificationId, $workerId);

        $this->assertTrue($result);

        Redis::del('notif:processing:' . $notificationId);
    }

    public function test_acquire_lock_returns_false_when_key_exists(): void
    {
        $notificationId = 'test-notification-2';

        Redis::set('notif:processing:' . $notificationId, 'other-worker', 'EX', 60);

        $result = $this->service->acquireLock($notificationId, 'new-worker');

        $this->assertFalse($result);

        Redis::del('notif:processing:' . $notificationId);
    }

    public function test_release_lock_removes_key_if_owner(): void
    {
        $notificationId = 'test-notification-3';
        $workerId       = 'worker-owner';

        Redis::set('notif:processing:' . $notificationId, $workerId, 'EX', 60);

        $this->service->releaseLock($notificationId, $workerId);

        $this->assertNull(Redis::get('notif:processing:' . $notificationId));
    }

    public function test_release_lock_does_not_remove_key_if_not_owner(): void
    {
        $notificationId = 'test-notification-4';

        Redis::set('notif:processing:' . $notificationId, 'other-worker', 'EX', 60);

        $this->service->releaseLock($notificationId, 'my-worker');

        $this->assertEquals('other-worker', Redis::get('notif:processing:' . $notificationId));

        Redis::del('notif:processing:' . $notificationId);
    }
}

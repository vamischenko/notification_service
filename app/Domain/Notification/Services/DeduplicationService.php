<?php

declare(strict_types=1);

namespace App\Domain\Notification\Services;

use Illuminate\Support\Facades\Redis;

class DeduplicationService
{
    private const PREFIX   = 'notif:processing:';
    private const LOCK_TTL = 60;

    public function acquireLock(string $notificationId, string $workerId): bool
    {
        $result = Redis::set(
            self::PREFIX . $notificationId,
            $workerId,
            'EX',
            self::LOCK_TTL,
            'NX',
        );

        return $result !== null && $result !== false;
    }

    public function releaseLock(string $notificationId, string $workerId): void
    {
        // Lua script: atomic check-owner-then-delete
        $script = <<<'LUA'
        if redis.call("GET", KEYS[1]) == ARGV[1] then
            return redis.call("DEL", KEYS[1])
        else
            return 0
        end
        LUA;

        Redis::eval($script, 1, self::PREFIX . $notificationId, $workerId);
    }
}

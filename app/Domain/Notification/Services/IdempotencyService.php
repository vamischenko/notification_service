<?php

declare(strict_types=1);

namespace App\Domain\Notification\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class IdempotencyService
{
    private const REDIS_PREFIX    = 'idempotency:';
    private const TTL_SECONDS     = 86400;
    private const PROCESSING_FLAG = '__processing__';

    public function getStoredResponse(string $key): ?array
    {
        $cached = Redis::get(self::REDIS_PREFIX . $key);

        if ($cached === null) {
            return null;
        }

        if ($cached === self::PROCESSING_FLAG) {
            return ['conflict' => true];
        }

        // Body is stored as JSON directly in Redis
        $data = json_decode($cached, true);

        return is_array($data) ? $data : null;
    }

    public function markProcessing(string $key): bool
    {
        $result = Redis::set(
            self::REDIS_PREFIX . $key,
            self::PROCESSING_FLAG,
            'EX',
            30,
            'NX',
        );

        return $result !== null && $result !== false;
    }

    public function storeResponse(string $key, array $body, int $statusCode): void
    {
        $payload = json_encode(['body' => $body, 'status_code' => $statusCode]);

        // Overwrite the processing flag with the actual response
        Redis::set(self::REDIS_PREFIX . $key, $payload, 'EX', self::TTL_SECONDS);

        // Persist to DB as durable audit log (best-effort, outside any active transaction)
        DB::table('idempotency_keys')->upsert(
            [
                'key'         => $key,
                'response'    => json_encode($body),
                'status_code' => $statusCode,
                'created_at'  => now(),
                'expires_at'  => now()->addSeconds(self::TTL_SECONDS),
            ],
            ['key'],
            ['response', 'status_code', 'expires_at'],
        );
    }
}

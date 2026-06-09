<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Redis;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'queue.default'                   => 'sync',
            'database.connections.pgsql.database' => 'notification_service_test',
            'database.connections.pgsql.host'   => 'postgres',
            'database.redis.default.host'     => 'redis',
        ]);

        try {
            Redis::flushDB();
        } catch (\Throwable) {
            // Redis may be unavailable in some environments
        }
    }
}

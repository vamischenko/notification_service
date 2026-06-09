<?php

/**
 * Force test env vars before Laravel boots.
 * Docker container env overrides phpunit.xml otherwise.
 */
$overrides = [
    'APP_ENV'          => 'testing',
    'DB_CONNECTION'    => 'pgsql',
    'DB_HOST'          => 'postgres',
    'DB_PORT'          => '5432',
    'DB_DATABASE'      => 'notification_service_test',
    'DB_USERNAME'      => 'app',
    'DB_PASSWORD'      => 'secret',
    'REDIS_HOST'       => 'redis',
    'REDIS_PORT'       => '6379',
    'QUEUE_CONNECTION' => 'sync',
    'CACHE_STORE'      => 'array',
];

foreach ($overrides as $key => $value) {
    $_ENV[$key]    = $value;
    $_SERVER[$key] = $value;
    putenv("{$key}={$value}");
}

require dirname(__DIR__) . '/vendor/autoload.php';

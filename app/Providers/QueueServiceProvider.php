<?php

declare(strict_types=1);

namespace App\Providers;

use App\Queue\RabbitMQConnector;
use Illuminate\Support\ServiceProvider;

class QueueServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app['queue']->addConnector('rabbitmq', function () {
            return new RabbitMQConnector();
        });
    }
}

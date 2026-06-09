<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Notification\Actions\ProcessNotificationAction;
use App\Domain\Notification\Contracts\NotificationRepositoryInterface;
use App\Domain\Notification\Contracts\RecipientRepositoryInterface;
use App\Gateways\Email\MockEmailGateway;
use App\Gateways\Sms\MockSmsGateway;
use App\Repositories\EloquentNotificationRepository;
use App\Repositories\EloquentRecipientRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(NotificationRepositoryInterface::class, EloquentNotificationRepository::class);
        $this->app->bind(RecipientRepositoryInterface::class, EloquentRecipientRepository::class);

        $this->app->bind('gateway.sms', function () {
            return new MockSmsGateway();
        });

        $this->app->bind('gateway.email', function () {
            return new MockEmailGateway();
        });

        $this->app->bind(ProcessNotificationAction::class, function ($app) {
            return new ProcessNotificationAction(
                smsGateway:   $app->make('gateway.sms'),
                emailGateway: $app->make('gateway.email'),
            );
        });
    }

    public function boot(): void {}
}

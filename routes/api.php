<?php

use App\Http\Controllers\Api\V1\NotificationBatchController;
use App\Http\Controllers\Api\V1\RecipientNotificationController;
use App\Http\Middleware\IdempotencyMiddleware;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::post(
        'notifications/batch',
        [NotificationBatchController::class, 'store']
    )->middleware(IdempotencyMiddleware::class);

    Route::get(
        'notifications/batches/{batchId}',
        [NotificationBatchController::class, 'show']
    );

    Route::get(
        'recipients/{recipientId}/notifications',
        [RecipientNotificationController::class, 'index']
    );
});

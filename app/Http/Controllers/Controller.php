<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Notification Service API',
    description: 'Микросервис массовых уведомлений SMS/Email с приоритизацией и дедупликацией',
)]
#[OA\Server(url: '/api', description: 'API Server')]
#[OA\Schema(
    schema: 'NotificationBatch',
    properties: [
        new OA\Property(property: 'batch_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'channel', type: 'string', enum: ['sms', 'email']),
        new OA\Property(property: 'priority', type: 'string', enum: ['transactional', 'marketing']),
        new OA\Property(property: 'message_text', type: 'string'),
        new OA\Property(property: 'total_count', type: 'integer'),
        new OA\Property(property: 'queued_count', type: 'integer'),
        new OA\Property(property: 'sent_count', type: 'integer'),
        new OA\Property(property: 'delivered_count', type: 'integer'),
        new OA\Property(property: 'discarded_count', type: 'integer'),
        new OA\Property(property: 'progress_percent', type: 'number'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
)]
#[OA\Schema(
    schema: 'Notification',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'batch_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'channel', type: 'string', enum: ['sms', 'email']),
        new OA\Property(property: 'priority', type: 'string', enum: ['transactional', 'marketing']),
        new OA\Property(property: 'message_text', type: 'string'),
        new OA\Property(property: 'status', type: 'string', enum: ['queued', 'sent', 'delivered', 'discarded']),
        new OA\Property(property: 'provider_message_id', type: 'string', nullable: true),
        new OA\Property(property: 'error_message', type: 'string', nullable: true),
        new OA\Property(property: 'attempts', type: 'integer'),
        new OA\Property(property: 'sent_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'delivered_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'discarded_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ],
)]
abstract class Controller {}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Notification\Actions\CreateBatchAction;
use App\Domain\Notification\Contracts\NotificationRepositoryInterface;
use App\Domain\Notification\DataTransferObjects\CreateBatchDTO;
use App\Domain\Notification\Enums\NotificationChannel;
use App\Domain\Notification\Enums\NotificationPriority;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SendBatchRequest;
use App\Http\Resources\Api\V1\NotificationBatchResource;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Batches', description: 'Управление массовыми рассылками')]
class NotificationBatchController extends Controller
{
    public function __construct(
        private readonly CreateBatchAction               $createBatch,
        private readonly NotificationRepositoryInterface $repo,
    ) {}

    #[OA\Post(
        path: '/api/v1/notifications/batch',
        summary: 'Запустить массовую рассылку',
        description: 'Создаёт батч и ставит уведомления в очередь. Поддерживает заголовок Idempotency-Key.',
        tags: ['Batches'],
        parameters: [
            new OA\Parameter(
                name: 'Idempotency-Key',
                in: 'header',
                required: false,
                description: 'UUID для идемпотентности. Повторный запрос с тем же ключом вернёт 200 с кэшированным ответом.',
                schema: new OA\Schema(type: 'string', format: 'uuid'),
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['channel', 'priority', 'message_text', 'recipient_ids'],
                properties: [
                    new OA\Property(property: 'channel', type: 'string', enum: ['sms', 'email'], example: 'sms'),
                    new OA\Property(property: 'priority', type: 'string', enum: ['transactional', 'marketing'], example: 'transactional'),
                    new OA\Property(property: 'message_text', type: 'string', maxLength: 1000, example: 'Ваш код доступа: 1234'),
                    new OA\Property(
                        property: 'recipient_ids',
                        type: 'array',
                        items: new OA\Items(type: 'string', format: 'uuid'),
                        minItems: 1,
                        maxItems: 1000,
                    ),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 202,
                description: 'Рассылка поставлена в очередь',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/NotificationBatch'),
                        new OA\Property(
                            property: 'meta',
                            properties: [new OA\Property(property: 'idempotent', type: 'boolean', example: false)],
                            type: 'object',
                        ),
                    ],
                ),
            ),
            new OA\Response(response: 200, description: 'Повторный запрос — идемпотентный ответ'),
            new OA\Response(response: 409, description: 'Запрос с этим Idempotency-Key уже обрабатывается'),
            new OA\Response(response: 422, description: 'Ошибка валидации'),
        ],
    )]
    public function store(SendBatchRequest $request): JsonResponse
    {
        $dto = new CreateBatchDTO(
            channel:        NotificationChannel::from($request->validated('channel')),
            priority:       NotificationPriority::from($request->validated('priority')),
            messageText:    $request->validated('message_text'),
            recipientIds:   $request->validated('recipient_ids'),
            idempotencyKey: $request->header('Idempotency-Key'),
        );

        [$batch, $isNew] = $this->createBatch->execute($dto);

        return (new NotificationBatchResource($batch))
            ->additional(['meta' => ['idempotent' => false]])
            ->response()
            ->setStatusCode($isNew ? 202 : 200);
    }

    #[OA\Get(
        path: '/api/v1/notifications/batches/{batchId}',
        summary: 'Получить статус батча',
        tags: ['Batches'],
        parameters: [
            new OA\Parameter(name: 'batchId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Статус батча', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'data', ref: '#/components/schemas/NotificationBatch')],
            )),
            new OA\Response(response: 404, description: 'Батч не найден'),
        ],
    )]
    public function show(string $batchId): JsonResponse
    {
        $batch = \App\Domain\Notification\Models\NotificationBatch::findOrFail($batchId);

        return (new NotificationBatchResource($batch))->response();
    }
}

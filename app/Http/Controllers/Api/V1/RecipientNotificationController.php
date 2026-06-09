<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Notification\Contracts\NotificationRepositoryInterface;
use App\Domain\Notification\Contracts\RecipientRepositoryInterface;
use App\Domain\Notification\Enums\NotificationChannel;
use App\Domain\Notification\Enums\NotificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Recipients', description: 'История уведомлений получателей')]
class RecipientNotificationController extends Controller
{
    public function __construct(
        private readonly NotificationRepositoryInterface $notificationRepo,
        private readonly RecipientRepositoryInterface    $recipientRepo,
    ) {}

    #[OA\Get(
        path: '/api/v1/recipients/{recipientId}/notifications',
        summary: 'История уведомлений получателя',
        tags: ['Recipients'],
        parameters: [
            new OA\Parameter(name: 'recipientId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['queued', 'sent', 'delivered', 'discarded'])),
            new OA\Parameter(name: 'channel', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['sms', 'email'])),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15, maximum: 100)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'История уведомлений',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Notification'),
                        ),
                        new OA\Property(property: 'meta', type: 'object'),
                        new OA\Property(property: 'links', type: 'object'),
                    ],
                ),
            ),
            new OA\Response(response: 404, description: 'Получатель не найден'),
            new OA\Response(response: 422, description: 'Ошибка валидации параметров'),
        ],
    )]
    public function index(Request $request, string $recipientId): JsonResponse
    {
        $request->validate([
            'status'   => ['nullable', 'string', \Illuminate\Validation\Rule::enum(NotificationStatus::class)],
            'channel'  => ['nullable', 'string', \Illuminate\Validation\Rule::enum(NotificationChannel::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $recipient = $this->recipientRepo->findById($recipientId);

        if ($recipient === null) {
            return response()->json(['message' => 'Recipient not found.'], 404);
        }

        $filters = array_filter([
            'status'  => $request->query('status'),
            'channel' => $request->query('channel'),
        ]);

        $perPage   = (int) $request->query('per_page', 15);
        $paginator = $this->notificationRepo->getRecipientNotifications($recipientId, $filters, $perPage);

        return NotificationResource::collection($paginator)->response();
    }
}

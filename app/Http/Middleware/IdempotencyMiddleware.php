<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Notification\Services\IdempotencyService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class IdempotencyMiddleware
{
    public function __construct(
        private readonly IdempotencyService $idempotency,
    ) {}

    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $key = $request->header('Idempotency-Key');

        if (empty($key)) {
            return $next($request);
        }

        $stored = $this->idempotency->getStoredResponse($key);

        if ($stored !== null) {
            if (isset($stored['conflict'])) {
                return response()->json(
                    ['message' => 'Request with this Idempotency-Key is still being processed.'],
                    Response::HTTP_CONFLICT
                );
            }

            $body = $stored['body'];
            $body['meta']['idempotent'] = true;

            return response()->json($body, Response::HTTP_OK);
        }

        // Mark as processing (30s TTL) — concurrent duplicate requests get 409
        if (! $this->idempotency->markProcessing($key)) {
            return response()->json(
                ['message' => 'Request with this Idempotency-Key is still being processed.'],
                Response::HTTP_CONFLICT
            );
        }

        /** @var \Illuminate\Http\JsonResponse $response */
        $response = $next($request);

        // Store on success (2xx)
        if ($response->getStatusCode() < 300) {
            $body = json_decode($response->getContent(), true) ?? [];
            $this->idempotency->storeResponse($key, $body, $response->getStatusCode());
        }

        return $response;
    }
}

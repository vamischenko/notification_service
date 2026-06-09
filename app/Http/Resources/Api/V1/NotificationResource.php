<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'batch_id'            => $this->batch_id,
            'channel'             => $this->channel->value,
            'priority'            => $this->priority->value,
            'message_text'        => $this->message_text,
            'status'              => $this->status->value,
            'provider_message_id' => $this->provider_message_id,
            'error_message'       => $this->error_message,
            'attempts'            => $this->attempts,
            'sent_at'             => $this->sent_at?->toIso8601String(),
            'delivered_at'        => $this->delivered_at?->toIso8601String(),
            'discarded_at'        => $this->discarded_at?->toIso8601String(),
            'created_at'          => $this->created_at?->toIso8601String(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'batch_id'        => $this->id,
            'channel'         => $this->channel->value,
            'priority'        => $this->priority->value,
            'message_text'    => $this->message_text,
            'total_count'     => $this->total_count,
            'queued_count'    => $this->queued_count,
            'sent_count'      => $this->sent_count,
            'delivered_count' => $this->delivered_count,
            'discarded_count' => $this->discarded_count,
            'progress_percent' => $this->total_count > 0
                ? round(($this->sent_count + $this->delivered_count + $this->discarded_count) / $this->total_count * 100, 1)
                : 0,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Notification\Models;

use App\Domain\Notification\Enums\NotificationChannel;
use App\Domain\Notification\Enums\NotificationPriority;
use App\Domain\Notification\Enums\NotificationStatus;
use App\Domain\Notification\Exceptions\InvalidStatusTransitionException;
use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'batch_id',
        'recipient_id',
        'channel',
        'priority',
        'message_text',
        'status',
        'idempotency_key',
        'provider_message_id',
        'error_message',
        'attempts',
        'last_attempt_at',
        'sent_at',
        'delivered_at',
        'discarded_at',
    ];

    protected $casts = [
        'channel'         => NotificationChannel::class,
        'priority'        => NotificationPriority::class,
        'status'          => NotificationStatus::class,
        'attempts'        => 'integer',
        'last_attempt_at' => 'datetime',
        'sent_at'         => 'datetime',
        'delivered_at'    => 'datetime',
        'discarded_at'    => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(NotificationBatch::class, 'batch_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Recipient::class);
    }

    public function transitionTo(NotificationStatus $status): void
    {
        if (! $this->status->canTransitionTo($status)) {
            throw new InvalidStatusTransitionException(
                "Cannot transition from {$this->status->value} to {$status->value}"
            );
        }

        $this->status = $status;

        match ($status) {
            NotificationStatus::SENT      => $this->sent_at = now(),
            NotificationStatus::DELIVERED => $this->delivered_at = now(),
            NotificationStatus::DISCARDED => $this->discarded_at = now(),
            default                       => null,
        };
    }

    protected static function newFactory(): NotificationFactory
    {
        return NotificationFactory::new();
    }
}

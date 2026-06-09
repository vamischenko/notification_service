<?php

declare(strict_types=1);

namespace App\Domain\Notification\Models;

use App\Domain\Notification\Enums\NotificationChannel;
use App\Domain\Notification\Enums\NotificationPriority;
use Database\Factories\NotificationBatchFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationBatch extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'notification_batches';

    protected $fillable = [
        'channel',
        'priority',
        'message_text',
        'total_count',
        'queued_count',
        'sent_count',
        'delivered_count',
        'discarded_count',
        'idempotency_key',
    ];

    protected $casts = [
        'channel'         => NotificationChannel::class,
        'priority'        => NotificationPriority::class,
        'total_count'     => 'integer',
        'queued_count'    => 'integer',
        'sent_count'      => 'integer',
        'delivered_count' => 'integer',
        'discarded_count' => 'integer',
    ];

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'batch_id');
    }

    protected static function newFactory(): NotificationBatchFactory
    {
        return NotificationBatchFactory::new();
    }
}

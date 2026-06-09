<?php

declare(strict_types=1);

namespace App\Domain\Notification\Models;

use Database\Factories\RecipientFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recipient extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = ['name', 'email', 'phone', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    protected static function newFactory(): RecipientFactory
    {
        return RecipientFactory::new();
    }
}

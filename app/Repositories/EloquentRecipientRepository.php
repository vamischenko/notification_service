<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Domain\Notification\Contracts\RecipientRepositoryInterface;
use App\Domain\Notification\Models\Recipient;
use Illuminate\Support\Collection;

class EloquentRecipientRepository implements RecipientRepositoryInterface
{
    public function findById(string $id): ?Recipient
    {
        return Recipient::find($id);
    }

    public function findActiveByIds(array $ids): Collection
    {
        return Recipient::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->get();
    }
}

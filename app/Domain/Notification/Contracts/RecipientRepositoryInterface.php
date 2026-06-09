<?php

declare(strict_types=1);

namespace App\Domain\Notification\Contracts;

use App\Domain\Notification\Models\Recipient;
use Illuminate\Support\Collection;

interface RecipientRepositoryInterface
{
    public function findById(string $id): ?Recipient;

    /** @return Collection<int, Recipient> */
    public function findActiveByIds(array $ids): Collection;
}

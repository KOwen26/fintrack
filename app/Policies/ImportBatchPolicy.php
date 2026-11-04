<?php

namespace App\Policies;

use App\Models\ImportBatch;
use App\Models\User;

/** Owner-only on the underlying account — importing mutates it, even when shareable. */
final class ImportBatchPolicy
{
    public function view(User $user, ImportBatch $batch): bool
    {
        return $this->canAccess($user, $batch);
    }

    public function update(User $user, ImportBatch $batch): bool
    {
        return $this->canAccess($user, $batch);
    }

    public function approve(User $user, ImportBatch $batch): bool
    {
        return $this->canAccess($user, $batch);
    }

    public function discard(User $user, ImportBatch $batch): bool
    {
        return $this->canAccess($user, $batch);
    }

    private function canAccess(User $user, ImportBatch $batch): bool
    {
        return $user->id === $batch->account->owner_id;
    }
}

<?php

namespace App\Policies;

use App\Models\Transfer;
use App\Models\User;

/**
 * Transfer-unit access is creator-based, mirroring TransactionPolicy:
 * writes are reserved to the creator alone, which keeps the unit cascade
 * creator-initiated (every member row shares the aggregate's creator).
 */
class TransferPolicy
{
    public function view(User $user, Transfer $transfer): bool
    {
        return $transfer->created_by === $user->id;
    }

    public function update(User $user, Transfer $transfer): bool
    {
        return $transfer->created_by === $user->id;
    }

    public function delete(User $user, Transfer $transfer): bool
    {
        return $transfer->created_by === $user->id;
    }
}

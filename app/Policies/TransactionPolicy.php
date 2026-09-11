<?php

namespace App\Policies;

use App\Enums\AccountAccessType;
use App\Models\Transaction;
use App\Models\User;

/**
 * Transaction access is creator-based, not account-based.
 *
 * A transaction is visible to its creator always, and to everyone else when
 * the owning account is shared (joint). Writes (update/delete) are reserved
 * to the creator alone — this is what keeps the transfer cascade safe.
 */
class TransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Transaction $transaction): bool
    {
        return $transaction->created_by === $user->id || $transaction->account->access_type === AccountAccessType::Joint;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Transaction $transaction): bool
    {
        return $transaction->created_by === $user->id;
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        return $transaction->created_by === $user->id;
    }
}

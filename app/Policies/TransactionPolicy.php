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
 * to the creator alone — this is what keeps the transfer cascade safe:
 * TransactionService::softDelete() removes every transaction sharing a
 * transfer_link_id, but createTransfer() stamps the same creator on all legs,
 * so a cascade can only ever be triggered by the user who created every
 * affected row. No per-account pair check is needed.
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

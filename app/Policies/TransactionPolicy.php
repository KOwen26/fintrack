<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    public function viewAny(User $user, Account $account): bool
    {
        return $user->can('view', $account);
    }

    public function view(User $user, Transaction $transaction): bool
    {
        return $user->can('view', $transaction->account);
    }

    public function create(User $user, Account $account): bool
    {
        return $user->can('view', $account);
    }

    public function update(User $user, Transaction $transaction): bool
    {
        return $transaction->account->owner_id === $user->id;
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        return $transaction->account->owner_id === $user->id;
    }
}

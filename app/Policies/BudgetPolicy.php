<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\Budget;
use App\Models\User;

class BudgetPolicy
{
    public function viewAny(User $user, Account $account): bool
    {
        return $user->can('view', $account);
    }

    public function create(User $user, Account $account): bool
    {
        return $account->owner_id === $user->id;
    }

    public function update(User $user, Budget $budget): bool
    {
        return $budget->account->owner_id === $user->id;
    }

    public function delete(User $user, Budget $budget): bool
    {
        return $budget->account->owner_id === $user->id;
    }
}

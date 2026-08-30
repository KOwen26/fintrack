<?php

namespace App\Policies;

use App\Enums\AccountAccessType;
use App\Models\Account;
use App\Models\User;

class AccountPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Account $account): bool
    {
        return $account->owner_id === $user->id || $account->access_type === AccountAccessType::Joint;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Account $account): bool
    {
        return $account->owner_id === $user->id;
    }

    public function delete(User $user, Account $account): bool
    {
        return $account->owner_id === $user->id;
    }

    public function restore(User $user, Account $account): bool
    {
        return $account->owner_id === $user->id;
    }

    public function archive(User $user, Account $account): bool
    {
        return $account->owner_id === $user->id;
    }
}

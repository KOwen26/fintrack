<?php

namespace App\Policies;

use App\Enums\AccountAccessType;
use App\Models\Account;
use App\Models\HouseholdMember;
use App\Models\User;

class AccountPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Account $account): bool
    {
        return $this->canAccess($user, $account);
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

    private function canAccess(User $user, Account $account): bool
    {
        if ($account->owner_id === $user->id) {
            return true;
        }

        if ($account->access_type !== AccountAccessType::Joint) {
            return false;
        }

        return HouseholdMember::query()
            ->where('household_id', $account->household_id)
            ->where('user_id', $user->id)
            ->whereNotNull('joined_at')
            ->exists();
    }
}

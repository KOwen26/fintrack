<?php

namespace App\Policies;

use App\Enums\HouseholdMemberRole;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;

class HouseholdPolicy
{
    public function view(User $user, Household $household): bool
    {
        return HouseholdMember::query()
            ->where('household_id', $household->id)
            ->where('user_id', $user->id)
            ->whereNotNull('joined_at')
            ->exists();
    }

    public function update(User $user, Household $household): bool
    {
        return $this->isOwner($user, $household);
    }

    public function invite(User $user, Household $household): bool
    {
        return $this->isOwner($user, $household);
    }

    public function removeMember(User $user, Household $household): bool
    {
        return $this->isOwner($user, $household);
    }

    private function isOwner(User $user, Household $household): bool
    {
        return HouseholdMember::query()
            ->where('household_id', $household->id)
            ->where('user_id', $user->id)
            ->where('role', HouseholdMemberRole::Owner->value)
            ->exists();
    }
}

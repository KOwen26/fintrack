<?php

namespace App\Services;

use App\Enums\HouseholdMemberRole;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\HouseholdMember;
use App\Models\User;
use Illuminate\Support\Str;

class HouseholdService
{
    public function create(User $user, string $name): Household
    {
        $household = Household::create([
            'name' => $name,
            'created_by' => $user->id,
        ]);

        $household->members()->create([
            'user_id' => $user->id,
            'role' => HouseholdMemberRole::Owner->value,
            'joined_at' => now(),
            'created_at' => now(),
        ]);

        return $household;
    }

    public function invite(Household $household, string $email, User $invitedBy): HouseholdInvitation
    {
        $household->invitations()
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->delete();

        return $household->invitations()->create([
            'invited_by' => $invitedBy->id,
            'email' => $email,
            'token' => Str::random(64),
            'expires_at' => now()->addHours(48),
            'created_at' => now(),
        ]);
    }

    public function acceptInvitation(HouseholdInvitation $invitation, User $user): HouseholdMember
    {
        $member = $invitation->household->members()->create([
            'user_id' => $user->id,
            'role' => HouseholdMemberRole::Member->value,
            'joined_at' => now(),
            'created_at' => now(),
        ]);

        $invitation->update(['accepted_at' => now()]);

        return $member;
    }

    public function removeMember(HouseholdMember $member): void
    {
        $member->delete();
    }
}

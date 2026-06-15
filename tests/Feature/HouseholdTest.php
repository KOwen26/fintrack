<?php

use App\Enums\HouseholdMemberRole;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a household and adds creator as owner', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('household.store'), ['name' => 'My Family'])
        ->assertRedirect(route('household.settings'));

    $household = Household::where('name', 'My Family')->first();
    expect($household)->not->toBeNull();

    $member = HouseholdMember::where('household_id', $household->id)->where('user_id', $user->id)->first();
    expect($member->role)->toBe(HouseholdMemberRole::Owner);
    expect($member->joined_at)->not->toBeNull();
});

it('shows household settings page with members', function (): void {
    $user = User::factory()->create();
    $household = Household::factory()->create(['created_by' => $user->id]);
    HouseholdMember::factory()->owner()->create(['household_id' => $household->id, 'user_id' => $user->id]);

    $this->actingAs($user)->get(route('household.settings'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('household/settings')
            ->has('household')
            ->where('household.name', $household->name)
        );
});

it('owner can send invitation', function (): void {
    $user = User::factory()->create();
    $household = Household::factory()->create(['created_by' => $user->id]);
    HouseholdMember::factory()->owner()->create(['household_id' => $household->id, 'user_id' => $user->id]);

    $this->actingAs($user)->post(route('household.invite'), ['email' => 'partner@example.com'])
        ->assertRedirect();

    expect($household->invitations()->where('email', 'partner@example.com')->exists())->toBeTrue();
});

it('member cannot remove other members', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $household = Household::factory()->create(['created_by' => $owner->id]);
    HouseholdMember::factory()->owner()->create(['household_id' => $household->id, 'user_id' => $owner->id]);
    $memberRecord = HouseholdMember::factory()->create(['household_id' => $household->id, 'user_id' => $member->id]);

    $this->actingAs($member)->delete(route('household.members.destroy', $memberRecord))
        ->assertForbidden();
});

<?php

use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\HouseholdMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows a valid invitation page', function (): void {
    $owner = User::factory()->create();
    $household = Household::factory()->create(['created_by' => $owner->id]);
    $invitation = HouseholdInvitation::factory()->create([
        'household_id' => $household->id,
        'invited_by' => $owner->id,
        'email' => 'partner@example.com',
    ]);
    $invitee = User::factory()->create(['email' => 'partner@example.com']);

    $this->actingAs($invitee)->get(route('household.invitations.show', $invitation->token))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('household/invitation'));
});

it('accepts an invitation and adds member to household', function (): void {
    $owner = User::factory()->create();
    $household = Household::factory()->create(['created_by' => $owner->id]);
    HouseholdMember::factory()->owner()->create(['household_id' => $household->id, 'user_id' => $owner->id]);
    $invitation = HouseholdInvitation::factory()->create([
        'household_id' => $household->id,
        'invited_by' => $owner->id,
    ]);
    $invitee = User::factory()->create();

    $this->actingAs($invitee)->post(route('household.invitations.accept', $invitation->token))
        ->assertRedirect(route('household.settings'));

    expect(HouseholdMember::where('user_id', $invitee->id)->where('household_id', $household->id)->exists())->toBeTrue();
    expect($invitation->fresh()->accepted_at)->not->toBeNull();
});

it('rejects acceptance of an expired invitation', function (): void {
    $owner = User::factory()->create();
    $household = Household::factory()->create(['created_by' => $owner->id]);
    $invitation = HouseholdInvitation::factory()->expired()->create([
        'household_id' => $household->id,
        'invited_by' => $owner->id,
    ]);
    $invitee = User::factory()->create();

    $this->actingAs($invitee)->post(route('household.invitations.accept', $invitation->token))
        ->assertStatus(422);
});

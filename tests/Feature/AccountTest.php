<?php

use App\Enums\AccountAccessType;
use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createUserWithHousehold(): array
{
    $user = User::factory()->create();
    $household = Household::factory()->create(['created_by' => $user->id]);
    HouseholdMember::factory()->owner()->create([
        'household_id' => $household->id,
        'user_id' => $user->id,
    ]);

    return [$user, $household];
}

it('lists only visible accounts for the authenticated user', function (): void {
    [$user, $household] = createUserWithHousehold();
    $personal = Account::factory()->create(['owner_id' => $user->id, 'household_id' => $household->id]);
    Account::factory()->create(); // different user — should not appear

    $this->actingAs($user)->get(route('accounts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('accounts/index')
            ->has('accounts', 1)
            ->where('accounts.0.id', $personal->id)
        );
});

it('stores a new personal account', function (): void {
    [$user, $household] = createUserWithHousehold();

    $this->actingAs($user)->post(route('accounts.store'), [
        'household_id' => $household->id,
        'name' => 'BCA Savings',
        'type' => AccountType::DebitAccount->value,
        'access_type' => AccountAccessType::Personal->value,
        'initial_balance' => 1_000_000,
        'currency' => 'IDR',
        'provider_id' => null,
        'credit_card_limit' => null,
        'cosmetics' => [
            'icon' => 'ph:wallet-bold',
            'color' => '#22c55e',
        ],
    ])->assertRedirect();

    $account = Account::where('name', 'BCA Savings')->first();

    expect($account)->not->toBeNull();
    expect($account->cosmetics)->toMatchArray(['icon' => 'ph:wallet-bold', 'color' => '#22c55e']);
});

it('prevents viewing another user personal account', function (): void {
    [$user] = createUserWithHousehold();
    $otherAccount = Account::factory()->create(['access_type' => AccountAccessType::Personal->value]);

    $this->actingAs($user)->get(route('accounts.show', $otherAccount))
        ->assertForbidden();
});

it('allows household member to view joint account', function (): void {
    [$owner, $household] = createUserWithHousehold();
    $member = User::factory()->create();
    HouseholdMember::factory()->create([
        'household_id' => $household->id,
        'user_id' => $member->id,
    ]);
    $joint = Account::factory()->joint()->create([
        'owner_id' => $owner->id,
        'household_id' => $household->id,
    ]);

    $this->actingAs($member)->get(route('accounts.show', $joint))
        ->assertOk();
});

it('archives an account', function (): void {
    [$user, $household] = createUserWithHousehold();
    $account = Account::factory()->create(['owner_id' => $user->id, 'household_id' => $household->id]);

    $this->actingAs($user)->post(route('accounts.archive', $account))
        ->assertRedirect(route('accounts.index'));

    expect($account->fresh()->archived_at)->not->toBeNull();
});

it('soft-deletes an account', function (): void {
    [$user, $household] = createUserWithHousehold();
    $account = Account::factory()->create(['owner_id' => $user->id, 'household_id' => $household->id]);

    $this->actingAs($user)->delete(route('accounts.destroy', $account))
        ->assertRedirect(route('accounts.index'));

    expect(Account::find($account->id))->toBeNull();
    expect(Account::withTrashed()->find($account->id))->not->toBeNull();
});

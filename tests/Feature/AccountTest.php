<?php

use App\Enums\AccountAccessType;
use App\Enums\AccountType;
use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists all accounts for any authenticated user', function (): void {
    $user = User::factory()->create();
    Account::factory()->create(['owner_id' => $user->id]);
    Account::factory()->create(); // owned by a different user

    $this->actingAs($user)->get(route('accounts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('accounts/index')
            ->has('accounts', 2)
        );
});

it('stores a new account', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('accounts.store'), [
        'name' => 'BCA Savings',
        'type' => AccountType::DebitAccount->value,
        'access_type' => AccountAccessType::Personal->value,
        'initial_balance' => 1_000_000,
        'currency' => 'IDR',
        'provider_id' => null,
        'credit_card_limit' => null,
        'decorations' => [
            'icon' => 'ph:wallet-bold',
            'color' => '#22c55e',
        ],
    ])->assertRedirect();

    $account = Account::where('name', 'BCA Savings')->first();
    expect($account)->not->toBeNull();
    expect($account->decorations)->toMatchArray(['icon' => 'ph:wallet-bold', 'color' => '#22c55e']);
});

it('allows any user to view any account', function (): void {
    $user = User::factory()->create();
    $otherAccount = Account::factory()->create(); // different owner

    $this->actingAs($user)->get(route('accounts.show', $otherAccount))
        ->assertOk();
});

it('prevents non-owner from editing an account', function (): void {
    $user = User::factory()->create();
    $otherAccount = Account::factory()->create(); // different owner

    $this->actingAs($user)->get(route('accounts.edit', $otherAccount))
        ->assertForbidden();
});

it('archives an account', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->post(route('accounts.archive', $account))
        ->assertRedirect(route('accounts.index'));

    expect($account->fresh()->archived_at)->not->toBeNull();
});

it('soft-deletes an account', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->delete(route('accounts.destroy', $account))
        ->assertRedirect(route('accounts.index'));

    expect(Account::find($account->id))->toBeNull();
    expect(Account::withTrashed()->find($account->id))->not->toBeNull();
});

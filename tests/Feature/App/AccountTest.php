<?php

use App\Enums\AccountAccessType;
use App\Enums\AccountType;
use App\Models\Account;
use App\Models\User;

test('lists only the accounts owned by the authenticated user', function (): void {
    $user = User::factory()->create();
    Account::factory()->create(['owner_id' => $user->id]);
    Account::factory()->create(); // owned by a different user

    $this->actingAs($user)->get(route('accounts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/account/index')
            ->has('accounts', 1)
        );
});

test('stores a new account and redirects to its page', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('accounts.store'), [
        'name' => 'BCA Savings',
        'type' => AccountType::DebitAccount->value,
        'access_type' => AccountAccessType::Personal->value,
        'initial_balance' => 1_000_000,
        'provider_id' => null,
        'decorations' => [
            'icon' => 'wallet-bold',
            'color' => 'green-500',
        ],
    ]);

    $account = Account::query()->where('name', 'BCA Savings')->first();
    expect($account)->not->toBeNull()
        ->and($account->decorations)->toMatchArray([
            'icon' => 'wallet-bold',
            'color' => 'green-500',
        ]);

    $response->assertRedirectToRoute('accounts.show', ['account' => $account]);
});

test('shows the account page for its owner', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->get(route('accounts.show', $account))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('app/account/show'));
});

test('shows a joint account to any authenticated user', function (): void {
    $user = User::factory()->create();
    $jointAccount = Account::factory()->joint()->create();

    $this->actingAs($user)->get(route('accounts.show', $jointAccount))
        ->assertOk();
});

test('forbids viewing a personal account owned by another user', function (): void {
    $user = User::factory()->create();
    $otherAccount = Account::factory()->create(); // different owner, personal

    $this->actingAs($user)->get(route('accounts.show', $otherAccount))
        ->assertForbidden();
});

test('prevents non-owner from editing an account', function (): void {
    $user = User::factory()->create();
    $otherAccount = Account::factory()->create(); // different owner

    $this->actingAs($user)->get(route('accounts.edit', $otherAccount))
        ->assertForbidden();
});

test('archives an account', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->post(route('accounts.archive', $account))
        ->assertRedirectToRoute('accounts.index');

    expect($account->fresh()->archived_at)->not->toBeNull();
});

test('forbids archiving an account owned by another user', function (): void {
    $user = User::factory()->create();
    $otherAccount = Account::factory()->create(); // different owner

    $this->actingAs($user)->post(route('accounts.archive', $otherAccount))
        ->assertForbidden();

    expect($otherAccount->fresh()->archived_at)->toBeNull();
});

test('soft-deletes an account', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->delete(route('accounts.destroy', $account))
        ->assertRedirectToRoute('accounts.index');

    expect(Account::find($account->id))->toBeNull()
        ->and(Account::withTrashed()->find($account->id))->not->toBeNull();
});

test('forbids deleting an account owned by another user', function (): void {
    $user = User::factory()->create();
    $otherAccount = Account::factory()->create(); // different owner

    $this->actingAs($user)->delete(route('accounts.destroy', $otherAccount))
        ->assertForbidden();

    expect($otherAccount->fresh()->deleted_at)->toBeNull();
});

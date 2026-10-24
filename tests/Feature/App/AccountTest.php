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

test('renders the account creation page', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('accounts.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/account/create')
            ->has('providers')
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

test('updates an account and redirects to its page', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->put(route('accounts.update', $account), [
        'name' => 'Renamed Savings',
        'type' => AccountType::DebitAccount->value,
        'provider_id' => null,
        'initial_balance' => 0,
        'decorations' => [
            'icon' => 'wallet-bold',
            'color' => 'green-500',
        ],
    ])->assertRedirectToRoute('accounts.show', ['account' => $account]);

    expect($account->fresh()->name)->toBe('Renamed Savings');
});

test('shows the account page for its owner', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->get(route('accounts.show', $account))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('app/account/show'));
});

test('shows a shareable account to any authenticated user', function (): void {
    $user = User::factory()->create();
    $shareableAccount = Account::factory()->shareable()->create();

    $this->actingAs($user)->get(route('accounts.show', $shareableAccount))
        ->assertOk();
});

test('forbids viewing a personal account owned by another user', function (): void {
    $user = User::factory()->create();
    $otherAccount = Account::factory()->create(); // different owner, personal

    $this->actingAs($user)->get(route('accounts.show', $otherAccount))
        ->assertForbidden();
});

test('renders the edit page for its owner', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->get(route('accounts.edit', $account))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/account/edit')
            ->has('providers')
        );
});

test('prevents non-owner from editing an account', function (): void {
    $user = User::factory()->create();
    $otherAccount = Account::factory()->create(); // different owner

    $this->actingAs($user)->get(route('accounts.edit', $otherAccount))
        ->assertForbidden();
});

test('forbids updating an account owned by another user', function (): void {
    $user = User::factory()->create();
    $otherAccount = Account::factory()->create(['name' => 'Their Savings']); // different owner

    $this->actingAs($user)->put(route('accounts.update', $otherAccount), [
        'name' => 'Hijacked Name',
        'type' => AccountType::DebitAccount->value,
        'provider_id' => null,
        'initial_balance' => 0,
        'decorations' => ['icon' => 'wallet-bold', 'color' => 'green-500'],
    ])->assertForbidden();

    expect($otherAccount->fresh()->name)->toBe('Their Savings');
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

test('restores an archived account', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->archived()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->post(route('accounts.restore', $account))
        ->assertRedirectToRoute('accounts.show', ['account' => $account]);

    expect($account->fresh()->archived_at)->toBeNull();
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

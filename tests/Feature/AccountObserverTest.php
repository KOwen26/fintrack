<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── created ─────────────────────────────────────────────────────

it('sets current_balance to initial_balance on create', function (): void {
    $account = Account::factory()->create([
        'initial_balance' => 1_000_000,
    ]);

    expect($account->fresh()->current_balance)->toEqual((float) 1_000_000);
});

it('sets current_balance to zero when initial_balance is zero', function (): void {
    $account = Account::factory()->create([
        'initial_balance' => 0,
    ]);

    expect($account->fresh()->current_balance)->toEqual((float) 0);
});

it('sets current_balance to negative initial_balance on create', function (): void {
    $account = Account::factory()->create([
        'initial_balance' => -500_000,
    ]);

    expect($account->fresh()->current_balance)->toEqual((float) -500_000);
});

// ── updated ─────────────────────────────────────────────────────

it('adjusts current_balance when initial_balance is increased', function (): void {
    $account = Account::factory()->create([
        'initial_balance' => 500_000,
    ]);

    expect($account->fresh()->current_balance)->toEqual((float) 500_000);

    $account->update(['initial_balance' => 1_000_000]);

    expect($account->fresh()->current_balance)->toEqual((float) 1_000_000);
});

it('adjusts current_balance when initial_balance is decreased', function (): void {
    $account = Account::factory()->create([
        'initial_balance' => 1_000_000,
    ]);

    expect($account->fresh()->current_balance)->toEqual((float) 1_000_000);

    $account->update(['initial_balance' => 300_000]);

    expect($account->fresh()->current_balance)->toEqual((float) 300_000);
});

it('adjusts current_balance from positive to zero', function (): void {
    $account = Account::factory()->create([
        'initial_balance' => 1_000_000,
    ]);

    expect($account->fresh()->current_balance)->toEqual((float) 1_000_000);

    $account->update(['initial_balance' => 0]);

    expect($account->fresh()->current_balance)->toEqual((float) 0);
});

it('adjusts current_balance from zero to positive', function (): void {
    $account = Account::factory()->create([
        'initial_balance' => 0,
    ]);

    expect($account->fresh()->current_balance)->toEqual((float) 0);

    $account->update(['initial_balance' => 750_000]);

    expect($account->fresh()->current_balance)->toEqual((float) 750_000);
});

it('does not change current_balance when other fields are updated', function (): void {
    $account = Account::factory()->create([
        'initial_balance' => 1_000_000,
    ]);

    expect($account->fresh()->current_balance)->toEqual((float) 1_000_000);

    $account->update(['name' => 'New Name']);

    expect($account->fresh()->current_balance)->toEqual((float) 1_000_000);
});

it('does not double-count on no-op initial_balance update', function (): void {
    $account = Account::factory()->create([
        'initial_balance' => 500_000,
    ]);

    expect($account->fresh()->current_balance)->toEqual((float) 500_000);

    // Update with same value
    $account->update(['initial_balance' => 500_000]);

    expect($account->fresh()->current_balance)->toEqual((float) 500_000);
});

it('preserves transaction-based balance changes when initial_balance is updated', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->create([
        'owner_id' => $user->id,
        'initial_balance' => 1_000_000,
    ]);

    expect($account->fresh()->current_balance)->toEqual((float) 1_000_000);

    // An expense transaction reduces current_balance via TransactionObserver
    $account->current_balance -= 200_000;
    $account->saveQuietly();

    expect($account->fresh()->current_balance)->toEqual((float) 800_000);

    // Now increase initial_balance — current_balance should adjust by delta
    $account->update(['initial_balance' => 1_500_000]);

    // Delta: +500_000, so current_balance: 800_000 + 500_000 = 1_300_000
    expect($account->fresh()->current_balance)->toEqual((float) 1_300_000);
});

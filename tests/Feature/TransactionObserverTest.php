<?php

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createBalanceAccount(): array
{
    $user = User::factory()->create();
    $account = Account::factory()->create([
        'owner_id' => $user->id,
        'initial_balance' => 0,
    ]);

    return [$user, $account];
}

// ── created ─────────────────────────────────────────────────────

it('increments account balance on income transaction created', function (): void {
    [$user, $account] = createBalanceAccount();

    Transaction::factory()->income()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'amount' => 500_000,
    ]);

    expect($account->fresh()->current_balance)->toEqual(500_000.0);
});

it('decrements account balance on expense transaction created', function (): void {
    [$user, $account] = createBalanceAccount();

    Transaction::factory()->expense()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'amount' => 250_000,
    ]);

    expect($account->fresh()->current_balance)->toEqual(-250_000.0);
});

it('adjusts balance correctly for transfer_in and transfer_out', function (): void {
    [$user, $source] = createBalanceAccount();
    $dest = Account::factory()->create(['owner_id' => $user->id, 'initial_balance' => 0]);

    Transaction::factory()->create([
        'account_id' => $source->id,
        'created_by' => $user->id,
        'amount' => 200_000,
        'type' => TransactionType::TransferOut->value,
        'transfer_link_id' => null,
    ]);

    Transaction::factory()->create([
        'account_id' => $dest->id,
        'created_by' => $user->id,
        'amount' => 200_000,
        'type' => TransactionType::TransferIn->value,
        'transfer_link_id' => null,
    ]);

    expect($source->fresh()->current_balance)->toEqual(-200_000.0);
    expect($dest->fresh()->current_balance)->toEqual(200_000.0);
});

it('handles fee transactions as outflows', function (): void {
    [$user, $account] = createBalanceAccount();

    Transaction::factory()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'amount' => 10_000,
        'type' => TransactionType::Fee->value,
        'transfer_link_id' => null,
    ]);

    expect($account->fresh()->current_balance)->toEqual(-10_000.0);
});

// ── updated ─────────────────────────────────────────────────────

it('adjusts balance when transaction amount is increased', function (): void {
    [$user, $account] = createBalanceAccount();

    $transaction = Transaction::factory()->expense()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'amount' => 100_000,
    ]);

    expect($account->fresh()->current_balance)->toEqual(-100_000.0);

    $transaction->update(['amount' => 150_000]);

    expect($account->fresh()->current_balance)->toEqual(-150_000.0);
});

it('adjusts balance when transaction amount is decreased', function (): void {
    [$user, $account] = createBalanceAccount();

    $transaction = Transaction::factory()->income()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'amount' => 500_000,
    ]);

    expect($account->fresh()->current_balance)->toEqual(500_000.0);

    $transaction->update(['amount' => 300_000]);

    expect($account->fresh()->current_balance)->toEqual(300_000.0);
});

it('does not double-count on no-op update', function (): void {
    [$user, $account] = createBalanceAccount();

    $transaction = Transaction::factory()->expense()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'amount' => 100_000,
    ]);

    expect($account->fresh()->current_balance)->toEqual(-100_000.0);

    // Update with same values
    $transaction->update(['amount' => 100_000]);

    expect($account->fresh()->current_balance)->toEqual(-100_000.0);
});

// ── deleted (soft delete) ───────────────────────────────────────

it('reverses balance impact when transaction is soft-deleted', function (): void {
    [$user, $account] = createBalanceAccount();

    $transaction = Transaction::factory()->income()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'amount' => 500_000,
    ]);

    expect($account->fresh()->current_balance)->toEqual(500_000.0);

    $transaction->delete();

    expect($account->fresh()->current_balance)->toEqual(0.0);
});

it('reverses expense impact when transaction is soft-deleted', function (): void {
    [$user, $account] = createBalanceAccount();

    $transaction = Transaction::factory()->expense()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'amount' => 250_000,
    ]);

    expect($account->fresh()->current_balance)->toEqual(-250_000.0);

    $transaction->delete();

    expect($account->fresh()->current_balance)->toEqual(0.0);
});

// ── restored ────────────────────────────────────────────────────

it('re-applies balance impact when transaction is restored', function (): void {
    [$user, $account] = createBalanceAccount();

    $transaction = Transaction::factory()->income()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'amount' => 500_000,
    ]);

    $transaction->delete();
    expect($account->fresh()->current_balance)->toEqual(0.0);

    $transaction->restore();

    expect($account->fresh()->current_balance)->toEqual(500_000.0);
});

it('re-applies expense impact when transaction is restored', function (): void {
    [$user, $account] = createBalanceAccount();

    $transaction = Transaction::factory()->expense()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'amount' => 100_000,
    ]);

    $transaction->delete();
    expect($account->fresh()->current_balance)->toEqual(0.0);

    $transaction->restore();

    expect($account->fresh()->current_balance)->toEqual(-100_000.0);
});

// ── mixed scenarios ─────────────────────────────────────────────

it('computes current_balance correctly across mixed transaction lifecycles', function (): void {
    [$user, $account] = createBalanceAccount();

    // Income 700k
    $income = Transaction::factory()->income()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'amount' => 700_000,
    ]);
    expect($account->fresh()->current_balance)->toEqual(700_000.0);

    // Expense 200k
    $expense = Transaction::factory()->expense()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'amount' => 200_000,
    ]);
    expect($account->fresh()->current_balance)->toEqual(500_000.0);

    // Update expense from 200k to 300k
    $expense->update(['amount' => 300_000]);
    expect($account->fresh()->current_balance)->toEqual(400_000.0);

    // Delete income (700k)
    $income->delete();
    expect($account->fresh()->current_balance)->toEqual(-300_000.0);

    // Restore income
    $income->restore();
    expect($account->fresh()->current_balance)->toEqual(400_000.0);
});

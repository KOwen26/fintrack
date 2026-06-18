<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function setupBalanceAccount(float $initialBalance = 0): array
{
    $user = User::factory()->create();
    $account = Account::factory()->create([
        'owner_id' => $user->id,
        'initial_balance' => $initialBalance,
    ]);

    return [$user, $account];
}

it('returns initial_balance when there are no transactions', function (): void {
    [, $account] = setupBalanceAccount(1_000_000);
    $service = new BalanceService;

    expect((float) $service->forAccount($account))->toBe(1_000_000.0);
});

it('adds income to initial balance', function (): void {
    [$user, $account] = setupBalanceAccount(1_000_000);
    Transaction::factory()->income()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'amount' => 500_000,
    ]);

    $service = new BalanceService;

    expect((float) $service->forAccount($account))->toBe(1_500_000.0);
});

it('subtracts expense from initial balance', function (): void {
    [$user, $account] = setupBalanceAccount(1_000_000);
    Transaction::factory()->expense()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'amount' => 250_000,
    ]);

    $service = new BalanceService;

    expect((float) $service->forAccount($account))->toBe(750_000.0);
});

it('computes balance correctly across mixed transaction types', function (): void {
    [$user, $account] = setupBalanceAccount(500_000);

    Transaction::factory()->income()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'amount' => 700_000,
    ]);
    Transaction::factory()->expense()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'amount' => 100_000,
    ]);
    Transaction::factory()->transferIn('link-123')->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'amount' => 200_000,
    ]);
    Transaction::factory()->transferOut('link-456')->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'amount' => 150_000,
    ]);

    $service = new BalanceService;

    expect((float) $service->forAccount($account))->toBe(1_150_000.0);
});

it('excludes soft-deleted transactions from balance', function (): void {
    [$user, $account] = setupBalanceAccount(1_000_000);
    $transaction = Transaction::factory()->expense()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'amount' => 100_000,
    ]);

    $transaction->delete();

    $service = new BalanceService;

    expect((float) $service->forAccount($account))->toBe(1_000_000.0);
});

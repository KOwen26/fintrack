<?php

use App\Enums\DatePeriodPreset;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SpendingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createAccountWithOwner(): Account
{
    $user = User::factory()->create();

    return Account::factory()->create(['owner_id' => $user->id]);
}

it('nests category spending under its group', function (): void {
    $account = createAccountWithOwner();
    Transaction::factory()->expense()->forCategory('groceries')->create([
        'account_id' => $account->id,
        'amount' => 200_000,
        'transaction_date' => now()->format('Y-m-d'),
    ]);

    $service = new SpendingService;
    $report = $service->globalCategorySpending(
        [$account->id],
        DatePeriodPreset::ThisMonth,
    );

    expect($report->categories)->toHaveCount(1);
    expect($report->categories[0])
        ->group_id->toBe('shopping')
        ->name->toBe('Shopping')
        ->total->toBe(200_000.0)
        ->children->toHaveCount(1);

    expect($report->categories[0]->children[0])
        ->category_id->toBe('groceries')
        ->name->toBe('Groceries')
        ->total->toBe(200_000.0);
});

it('returns an empty report when no accounts are given', function (): void {
    $service = new SpendingService;

    $report = $service->globalCategorySpending(
        [],
        DatePeriodPreset::ThisMonth,
    );

    expect($report->categories)->toBe([])
        ->and($report->period_total)->toBe(0.0);
});

it('merges sibling category spending into one group', function (): void {
    $account = createAccountWithOwner();

    Transaction::factory()->expense()->forCategory('fuel')->create([
        'account_id' => $account->id,
        'amount' => 300_000,
        'transaction_date' => now()->format('Y-m-d'),
    ]);

    Transaction::factory()->expense()->forCategory('public_transport')->create([
        'account_id' => $account->id,
        'amount' => 150_000,
        'transaction_date' => now()->format('Y-m-d'),
    ]);

    $service = new SpendingService;
    $report = $service->globalCategorySpending(
        [$account->id],
        DatePeriodPreset::ThisMonth,
    );

    expect($report->categories)->toHaveCount(1);
    expect($report->categories[0])
        ->group_id->toBe('transport')
        ->name->toBe('Transport')
        ->total->toBe(450_000.0);

    expect($report->categories[0]->children)->toHaveCount(2);
    expect($report->categories[0]->children[0])
        ->category_id->toBe('fuel')
        ->total->toBe(300_000.0);
    expect($report->categories[0]->children[1])
        ->category_id->toBe('public_transport')
        ->total->toBe(150_000.0);
});

it('orders groups by total descending', function (): void {
    $account = createAccountWithOwner();

    Transaction::factory()->expense()->forCategory('groceries')->create([
        'account_id' => $account->id,
        'amount' => 200_000,
        'transaction_date' => now()->format('Y-m-d'),
    ]);

    Transaction::factory()->expense()->forCategory('fuel')->create([
        'account_id' => $account->id,
        'amount' => 300_000,
        'transaction_date' => now()->format('Y-m-d'),
    ]);

    $service = new SpendingService;
    $report = $service->globalCategorySpending(
        [$account->id],
        DatePeriodPreset::ThisMonth,
    );

    expect($report->categories)->toHaveCount(2);
    expect($report->categories[0]->group_id)->toBe('transport');
    expect($report->categories[1]->group_id)->toBe('shopping');
});

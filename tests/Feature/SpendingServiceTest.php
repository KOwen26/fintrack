<?php

use App\Models\Account;
use App\Models\Category;
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

it('returns a single top-level category as a group without children', function (): void {
    $account = createAccountWithOwner();
    $category = Category::factory()->create(['name' => 'Groceries']);
    Transaction::factory()->expense()->forCategory($category->id)->create([
        'account_id' => $account->id,
        'amount' => 100_000,
        'transaction_date' => now()->format('Y-m-d'),
    ]);

    $service = new SpendingService;
    $report = $service->globalCategorySpending(
        [$account->id],
        now()->startOfMonth()->toDateString(),
        now()->endOfMonth()->toDateString(),
    );

    expect($report->categories)->toHaveCount(1);
    expect($report->categories[0])
        ->categoryId->toBe((int) $category->id)
        ->name->toBe('Groceries')
        ->children->toBe([]);
});

it('returns child category nested under its parent group', function (): void {
    $account = createAccountWithOwner();

    $parent = Category::factory()->create(['name' => 'Food & Drink']);
    $child = Category::factory()->child($parent->id)->create(['name' => 'Restaurant']);

    Transaction::factory()->expense()->forCategory($child->id)->create([
        'account_id' => $account->id,
        'amount' => 200_000,
        'transaction_date' => now()->format('Y-m-d'),
    ]);

    $service = new SpendingService;
    $report = $service->globalCategorySpending(
        [$account->id],
        now()->startOfMonth()->toDateString(),
        now()->endOfMonth()->toDateString(),
    );

    expect($report->categories)->toHaveCount(1);
    expect($report->categories[0])
        ->categoryId->toBe((int) $parent->id)
        ->name->toBe('Food & Drink');

    expect($report->categories[0]->children)->toHaveCount(1);
    expect($report->categories[0]->children[0])
        ->categoryId->toBe((int) $child->id)
        ->name->toBe('Restaurant');
});

it('merges parent direct spending and child spending into one group', function (): void {
    $account = createAccountWithOwner();

    $parent = Category::factory()->create(['name' => 'Transport']);
    $child = Category::factory()->child($parent->id)->create(['name' => 'Gas']);

    // Direct spending on parent category
    Transaction::factory()->expense()->forCategory($parent->id)->create([
        'account_id' => $account->id,
        'amount' => 300_000,
        'transaction_date' => now()->format('Y-m-d'),
    ]);

    // Child spending
    Transaction::factory()->expense()->forCategory($child->id)->create([
        'account_id' => $account->id,
        'amount' => 150_000,
        'transaction_date' => now()->format('Y-m-d'),
    ]);

    $service = new SpendingService;
    $report = $service->globalCategorySpending(
        [$account->id],
        now()->startOfMonth()->toDateString(),
        now()->endOfMonth()->toDateString(),
    );

    // Both should be merged into 1 group
    expect($report->categories)->toHaveCount(1);
    expect($report->categories[0])
        ->name->toBe('Transport')
        ->total->toBe(450_000.0);

    // Child should be nested
    expect($report->categories[0]->children)->toHaveCount(1);
    expect($report->categories[0]->children[0])
        ->name->toBe('Gas')
        ->total->toBe(150_000.0);
});

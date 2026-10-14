<?php

use App\Data\Report\CategorySpendingItemData;
use App\Enums\Category;
use App\Enums\DatePeriodPreset;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SpendingService;

function createAccountWithOwner(): Account
{
    $user = User::factory()->create();

    return Account::factory()->create(['owner_id' => $user->id]);
}

function spendingItem(string $categoryId, string $group, float $total, float $percentage): CategorySpendingItemData
{
    $category = Category::from($categoryId);

    return new CategorySpendingItemData(
        category_id: $category->value,
        group: $group,
        name: $category->label(),
        color: $category->decorations()->color,
        icon: $category->decorations()->icon,
        total: $total,
        percentage: $percentage,
    );
}

describe('globalCategorySpending', function (): void {
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

        expect($report->categories)->toHaveCount(1)
            ->and($report->categories[0])->group_id->toBe('shopping')->name->toBe('Shopping')->total->toBe(200_000.0)->children->toHaveCount(1)
            ->and($report->categories[0]->children[0])->category_id->toBe('groceries')->name->toBe('Groceries')->total->toBe(200_000.0);
    });

    it('returns an empty report when no accounts are given', function (): void {
        $service = new SpendingService;

        $report = $service->globalCategorySpending(
            [],
            DatePeriodPreset::ThisMonth,
        );

        expect($report->categories)->toBeEmpty()
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

        expect($report->categories)->toHaveCount(1)
            ->and($report->categories[0])->group_id->toBe('transport')->name->toBe('Transport')->total->toBe(450_000.0)
            ->and($report->categories[0]->children)->toHaveCount(2)
            ->and(array_column($report->categories[0]->children, 'total'))->toBe([300_000.0, 150_000.0]);
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

        expect($report->categories)->toHaveCount(2)
            ->and($report->categories[0]->group_id)->toBe('transport')
            ->and($report->categories[1]->group_id)->toBe('shopping');
    });
});

describe('groupByCategoryGroup', function (): void {
    it('returns empty array for no items', function (): void {
        $service = new SpendingService;

        $result = $service->groupByCategoryGroup([], 1000);

        expect($result)->toBeEmpty();
    });

    it('groups a single category under its group without extra nesting', function (): void {
        $service = new SpendingService;

        $items = [spendingItem('dining_out', 'food_and_drinks', 500.0, 50.0)];

        $result = $service->groupByCategoryGroup($items, 1000);

        expect($result)->toHaveCount(1)
            ->and($result[0])->group_id->toBe('food_and_drinks')->name->toBe('Food & Drinks')->total->toBe(500.0)->percentage->toBe(50.0)->children->toHaveCount(1)
            ->and($result[0]->children[0])->category_id->toBe('dining_out')->total->toBe(500.0);
    });

    it('merges sibling categories into one group', function (): void {
        $service = new SpendingService;

        $items = [
            spendingItem('fuel', 'transport', 300.0, 30.0),
            spendingItem('public_transport', 'transport', 150.0, 15.0),
        ];

        $result = $service->groupByCategoryGroup($items, 1000);

        expect($result)->toHaveCount(1)
            ->and($result[0])->group_id->toBe('transport')->name->toBe('Transport')->total->toBe(450.0)->percentage->toBe(45.0);

        // Child percentages are relative to the group subtotal (450)
        expect($result[0]->children)->toHaveCount(2);
        expect(array_column($result[0]->children, 'percentage'))->toBe([66.67, 33.33]);
    });

    it('handles multiple groups sorted by total descending', function (): void {
        $service = new SpendingService;

        $items = [
            spendingItem('fuel', 'transport', 300.0, 30.0),
            spendingItem('groceries', 'shopping', 500.0, 50.0),
        ];

        $result = $service->groupByCategoryGroup($items, 1000);

        expect($result)->toHaveCount(2);
        // Shopping comes first (higher total)
        expect($result[0]->group_id)->toBe('shopping');
        expect($result[1]->group_id)->toBe('transport')
            ->and($result[0]->total)->toBe(500.0)
            ->and($result[1]->total)->toBe(300.0);
    });

    it('recalculates children percentages relative to the group subtotal', function (): void {
        $service = new SpendingService;

        $items = [
            spendingItem('fuel', 'transport', 300.0, 30.0),
            spendingItem('public_transport', 'transport', 100.0, 10.0),
            spendingItem('bus_trains', 'transport', 100.0, 10.0),
        ];

        $result = $service->groupByCategoryGroup($items, 1000);

        expect($result)->toHaveCount(1)
            ->and($result[0]->total)->toBe(500.0)
            ->and($result[0]->children)->toHaveCount(3);
        // 300 / 500 = 60%
        expect($result[0]->children[0]->percentage)->toBe(60.0);
        // 100 / 500 = 20%
        expect($result[0]->children[1]->percentage)->toBe(20.0);
        expect($result[0]->children[2]->percentage)->toBe(20.0);
    });
});

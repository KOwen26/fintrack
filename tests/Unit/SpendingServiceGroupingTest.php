<?php

use App\Data\Report\CategorySpendingItemData;
use App\Enums\Category;
use App\Services\SpendingService;

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

it('returns empty array for no items', function (): void {
    $service = new SpendingService;

    $result = $service->groupByCategoryGroup([], 1000);

    expect($result)->toBe([]);
});

it('groups a single category under its group without extra nesting', function (): void {
    $service = new SpendingService;

    $items = [spendingItem('dining_out', 'food_and_drinks', 500.0, 50.0)];

    $result = $service->groupByCategoryGroup($items, 1000);

    expect($result)->toHaveCount(1);
    expect($result[0])
        ->group_id->toBe('food_and_drinks')
        ->name->toBe('Food & Drinks')
        ->total->toBe(500.0)
        ->percentage->toBe(50.0)
        ->children->toHaveCount(1);

    expect($result[0]->children[0])
        ->category_id->toBe('dining_out')
        ->total->toBe(500.0);
});

it('merges sibling categories into one group', function (): void {
    $service = new SpendingService;

    $items = [
        spendingItem('fuel', 'transport', 300.0, 30.0),
        spendingItem('public_transport', 'transport', 150.0, 15.0),
    ];

    $result = $service->groupByCategoryGroup($items, 1000);

    expect($result)->toHaveCount(1);
    expect($result[0])
        ->group_id->toBe('transport')
        ->name->toBe('Transport')
        ->total->toBe(450.0)
        ->percentage->toBe(45.0);

    // Child percentages are relative to the group subtotal (450)
    expect($result[0]->children)->toHaveCount(2);
    expect($result[0]->children[0])
        ->category_id->toBe('fuel')
        ->percentage->toBe(66.67);
    expect($result[0]->children[1])
        ->category_id->toBe('public_transport')
        ->percentage->toBe(33.33);
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
    expect($result[1]->group_id)->toBe('transport');
    expect($result[0]->total)->toBe(500.0);
    expect($result[1]->total)->toBe(300.0);
});

it('recalculates children percentages relative to the group subtotal', function (): void {
    $service = new SpendingService;

    $items = [
        spendingItem('fuel', 'transport', 300.0, 30.0),
        spendingItem('public_transport', 'transport', 100.0, 10.0),
        spendingItem('bus_trains', 'transport', 100.0, 10.0),
    ];

    $result = $service->groupByCategoryGroup($items, 1000);

    expect($result)->toHaveCount(1);
    expect($result[0]->total)->toBe(500.0);

    expect($result[0]->children)->toHaveCount(3);
    // 300 / 500 = 60%
    expect($result[0]->children[0]->percentage)->toBe(60.0);
    // 100 / 500 = 20%
    expect($result[0]->children[1]->percentage)->toBe(20.0);
    expect($result[0]->children[2]->percentage)->toBe(20.0);
});

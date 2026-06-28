<?php

use App\Data\Report\CategorySpendingItemData;
use App\Services\SpendingService;

it('returns empty array for no items', function (): void {
    $service = new SpendingService;

    $result = $service->groupByParent([], 1000);

    expect($result)->toBe([]);
});

it('groups a single top-level category without children', function (): void {
    $service = new SpendingService;

    $items = [
        new CategorySpendingItemData(
            name: 'Food',
            color: '#ff0000',
            icon: 'ph--fork-knife',
            total: 500.0,
            percentage: 50.0,
            categoryId: 1,
            parentId: null,
            parentName: null,
        ),
    ];

    $result = $service->groupByParent($items, 1000);

    expect($result)->toHaveCount(1);
    expect($result[0])
        ->categoryId->toBe(1)
        ->name->toBe('Food')
        ->total->toBe(500.0)
        ->percentage->toBe(50.0)
        ->children->toBe([]);
});

it('merges parent with its child into one group', function (): void {
    $service = new SpendingService;

    $items = [
        new CategorySpendingItemData(
            name: 'Transport',
            color: '#00ff00',
            icon: 'ph--car',
            total: 300.0,
            percentage: 30.0,
            categoryId: 1,
            parentId: null,
            parentName: null,
        ),
        new CategorySpendingItemData(
            name: 'Gas',
            color: '#0000ff',
            icon: 'ph--gas-pump',
            total: 150.0,
            percentage: 15.0,
            categoryId: 2,
            parentId: 1,
            parentName: 'Transport',
        ),
    ];

    $result = $service->groupByParent($items, 1000);

    expect($result)->toHaveCount(1);
    expect($result[0])
        ->categoryId->toBe(1)
        ->name->toBe('Transport')
        ->total->toBe(450.0)
        ->percentage->toBe(45.0);

    // Child percentage should now be relative to parent subtotal (450)
    expect($result[0]->children)->toHaveCount(1);
    expect($result[0]->children[0])
        ->categoryId->toBe(2)
        ->name->toBe('Gas')
        ->total->toBe(150.0)
        ->percentage->toBe(33.33);
});

it('synthesises parent group from children when parent has no direct spending', function (): void {
    $service = new SpendingService;

    $items = [
        new CategorySpendingItemData(
            name: 'Restaurant',
            color: '#ff00ff',
            icon: 'ph--utensils',
            total: 200.0,
            percentage: 20.0,
            categoryId: 3,
            parentId: 2,
            parentName: 'Food & Drink',
        ),
        new CategorySpendingItemData(
            name: 'Coffee',
            color: '#ffff00',
            icon: 'ph--coffee',
            total: 50.0,
            percentage: 5.0,
            categoryId: 4,
            parentId: 2,
            parentName: 'Food & Drink',
        ),
    ];

    $result = $service->groupByParent($items, 1000);

    expect($result)->toHaveCount(1);
    expect($result[0])
        ->categoryId->toBe(2)
        ->name->toBe('Food & Drink')
        ->total->toBe(250.0)
        ->percentage->toBe(25.0);

    // Uses first child's color/icon as fallback
    expect($result[0]->color)->toBe('#ff00ff');
    expect($result[0]->icon)->toBe('ph--utensils');
    expect($result[0]->children)->toHaveCount(2);
});

it('handles multiple parent groups sorted by total descending', function (): void {
    $service = new SpendingService;

    $items = [
        new CategorySpendingItemData(
            name: 'Transport',
            color: '#00ff00',
            icon: 'ph--car',
            total: 100.0,
            percentage: 10.0,
            categoryId: 1,
            parentId: null,
            parentName: null,
        ),
        new CategorySpendingItemData(
            name: 'Food',
            color: '#ff0000',
            icon: 'ph--fork-knife',
            total: 500.0,
            percentage: 50.0,
            categoryId: 2,
            parentId: null,
            parentName: null,
        ),
    ];

    $result = $service->groupByParent($items, 1000);

    expect($result)->toHaveCount(2);
    // Food should come first (higher total)
    expect($result[0]->name)->toBe('Food');
    expect($result[1]->name)->toBe('Transport');
    expect($result[0]->total)->toBe(500.0);
    expect($result[1]->total)->toBe(100.0);
});

it('recalculates child percentages relative to parent subtotal', function (): void {
    $service = new SpendingService;

    $items = [
        new CategorySpendingItemData(
            name: 'Transport',
            color: '#00ff00',
            icon: 'ph--car',
            total: 300.0,
            percentage: 30.0,
            categoryId: 1,
            parentId: null,
            parentName: null,
        ),
        new CategorySpendingItemData(
            name: 'Gas',
            color: '#0000ff',
            icon: 'ph--gas-pump',
            total: 100.0,
            percentage: 10.0,
            categoryId: 2,
            parentId: 1,
            parentName: 'Transport',
        ),
        new CategorySpendingItemData(
            name: 'Toll',
            color: '#ff00ff',
            icon: 'ph--road',
            total: 100.0,
            percentage: 10.0,
            categoryId: 3,
            parentId: 1,
            parentName: 'Transport',
        ),
    ];

    $result = $service->groupByParent($items, 1000);

    expect($result)->toHaveCount(1);
    expect($result[0]->total)->toBe(500.0);

    expect($result[0]->children)->toHaveCount(2);
    // Gas: 100 / 500 = 20%
    expect($result[0]->children[0]->percentage)->toBe(20.0);
    // Toll: 100 / 500 = 20%
    expect($result[0]->children[1]->percentage)->toBe(20.0);
});

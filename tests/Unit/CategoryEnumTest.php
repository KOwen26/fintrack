<?php

use App\Enums\Cashflow;
use App\Enums\Category;
use App\Enums\CategoryGroup;
use App\Objects\Decoration;

it('declares the full preset inventory', function (): void {
    expect(count(Category::cases()))->toBe(56)
        ->and(count(CategoryGroup::cases()))->toBe(11)
        ->and(count(Category::bookable()))->toBe(55)
        ->and(Category::bookable())->not->toContain(Category::InitialBalance);
});

it('resolves metadata for every case without throwing', function (): void {
    foreach (Category::cases() as $category) {
        expect($category->label())->not->toBeEmpty()
            ->and($category->decorations())->toBeInstanceOf(Decoration::class)
            ->and($category->decorations()->icon)->not->toBeEmpty()
            ->and($category->decorations()->color)->not->toBeEmpty()
            ->and($category->group())->toBeInstanceOf(CategoryGroup::class);
    }
});

it('keeps group children consistent with group() back-references', function (): void {
    foreach (CategoryGroup::cases() as $group) {
        foreach ($group->children() as $category) {
            expect($category->group())->toBe($group);
        }
    }

    $grouped = array_map(fn (Category $c): string => $c->value, CategoryGroup::Income->children());
    expect($grouped)->toContain(Category::Salary, Category::InitialBalance)
        ->and(CategoryGroup::Income->flow())->toBe(Cashflow::Inflow)
        ->and(CategoryGroup::Finance->flow())->toBe(Cashflow::Outflow);
});

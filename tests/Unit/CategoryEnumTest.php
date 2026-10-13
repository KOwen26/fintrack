<?php

use App\Enums\Cashflow;
use App\Enums\Category;
use App\Enums\CategoryGroup;
use App\Objects\Decoration;

it('declares the full preset inventory', function (): void {
    expect(Category::cases())->toHaveCount(56)
        ->and(CategoryGroup::cases())->toHaveCount(11)
        ->and(Category::bookable())->toHaveCount(55)
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

    expect(CategoryGroup::Income->children())->toContain(Category::Salary, Category::InitialBalance)
        ->and(CategoryGroup::Income->flow())->toBe(Cashflow::Inflow)
        ->and(CategoryGroup::Finance->flow())->toBe(Cashflow::Outflow);
});

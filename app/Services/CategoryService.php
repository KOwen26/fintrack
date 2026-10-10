<?php

namespace App\Services;

use App\Data\Category\CategoryData;
use App\Enums\Cashflow;
use App\Enums\Category;
use App\Enums\CategoryGroup;
use Illuminate\Support\Collection;

final class CategoryService
{
    /** Alias of getBookableCategories — the flat catalog under its original name. */
    public static function getCategories(): Collection
    {
        return self::toData(collect(Category::cases())
            ->reject(fn (Category $category): bool => $category === Category::InitialBalance));
    }

    /** Flat inflow half of the bookable catalog. */
    public static function getInflowCategories(): Collection
    {
        return self::toData(self::casesForFlow(Cashflow::Inflow));
    }

    /** Flat outflow half of the bookable catalog. */
    public static function getOutflowCategories(): Collection
    {
        return self::toData(self::casesForFlow(Cashflow::Outflow));
    }

    /** Group rows for every group, in display order. */
    public static function getGroupedCategories(): Collection
    {
        return self::groupRows(collect(CategoryGroup::cases()));
    }

    /** Group rows for the inflow side (the Income group). */
    public static function getGroupedInflowCategories(): Collection
    {
        return self::groupRows(self::groupsForFlow(Cashflow::Inflow));
    }

    /** Group rows for the outflow side (every non-Income group). */
    public static function getGroupedOutflowCategories(): Collection
    {
        return self::groupRows(self::groupsForFlow(Cashflow::Outflow));
    }

    private static function casesForFlow(Cashflow $cashflow): Collection
    {
        return collect(Category::cases())
            ->filter(fn (Category $category): bool => $category->group()->flow() === $cashflow)
            ->reject(fn (Category $category): bool => $category === Category::InitialBalance);
    }

    private static function groupsForFlow(Cashflow $cashflow): Collection
    {
        return collect(CategoryGroup::cases())
            ->filter(fn (CategoryGroup $group): bool => $group->flow() === $cashflow);
    }

    private static function toData(Collection $categories): Collection
    {
        return $categories
            ->map(fn (Category $category): CategoryData => CategoryData::fromEnum($category))
            ->values();
    }

    private static function groupRows(Collection $groups): Collection
    {
        return $groups
            ->map(fn (CategoryGroup $group): array => [
                'id' => $group->value,
                'name' => $group->label(),
                'cashflow' => $group->flow()->value,
                'decorations' => $group->decorations(),
                'options' => self::toData(collect($group->children())),
            ])
            ->values();
    }
}

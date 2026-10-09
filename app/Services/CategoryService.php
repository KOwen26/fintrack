<?php

namespace App\Services;

use App\Data\Category\CategoryData;
use App\Enums\Category;
use App\Enums\CategoryGroup;
use Illuminate\Support\Collection;

class CategoryService
{
    /** Flat bookable catalog — the transaction form's selectable options. */
    public static function getCategories(): Collection
    {
        return self::getBookableCategories();
    }

    /** Grouped catalog rows with their child options, in display order. */
    public static function getGroupedCategories(): Collection
    {
        return collect(CategoryGroup::cases())
            ->map(fn (CategoryGroup $group): array => [
                'id' => $group->value,
                'name' => $group->label(),
                'type' => $group->type()->value,
                'decorations' => $group->decorations(),
                'options' => collect($group->children())
                    ->map(fn (Category $category): CategoryData => CategoryData::fromEnum($category))
                    ->all(),
            ])
            ->values();
    }

    /** Flat bookable catalog — everything except the system-only opening balance. */
    public static function getBookableCategories(): Collection
    {
        return collect(Category::bookable())
            ->map(fn (string $value): CategoryData => CategoryData::fromEnum(Category::from($value)))
            ->values();
    }
}

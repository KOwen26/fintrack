<?php

namespace App\Data\Report;

use App\Enums\Category;
use Spatie\LaravelData\Data;

class CategorySpendingItemData extends Data
{
    public function __construct(
        public string $category_id,
        public string $group,
        public string $name,
        public string $color,
        public string $icon,
        public float $total,
        public float $percentage,
    ) {}

    /** Derive the presentation fields from a preset category; totals come from the query. */
    public static function fromEnum(Category $category, float $total, float $percentage): self
    {
        return new self(
            category_id: $category->value,
            group: $category->group()->value,
            name: $category->label(),
            color: $category->decorations()->color,
            icon: $category->decorations()->icon,
            total: $total,
            percentage: $percentage,
        );
    }
}

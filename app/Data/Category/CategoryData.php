<?php

namespace App\Data\Category;

use App\Data\DecorationData;
use App\Enums\Category;
use App\Enums\CategoryType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One preset category, shaped like the table row it replaced. */
#[TypeScript]
class CategoryData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public CategoryType $type,
        public DecorationData $decorations,
        public bool $is_fixed_cost,
        public CategoryGroupData $group,
    ) {}

    public static function fromEnum(Category $category): self
    {
        return new self(
            id: $category->value,
            name: $category->label(),
            decorations: $category->decorations(),
            type: $category->group()->type(),
            is_fixed_cost: $category->isFixedCost(),
            group: CategoryGroupData::fromEnum($category->group()),
        );
    }
}

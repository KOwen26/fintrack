<?php

namespace App\Data\Category;

use App\Enums\Cashflow;
use App\Enums\Category;
use App\Objects\Decoration;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One preset category, shaped like the table row it replaced. */
#[TypeScript]
class CategoryData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public Cashflow $cashflow,
        public Decoration $decorations,
        public CategoryGroupData | string $group,
    ) {}

    public static function fromEnum(Category $category, bool $withGroupData = true): self
    {
        return new self(
            id: $category->value,
            name: $category->label(),
            cashflow: $category->group()->flow(),
            decorations: $category->decorations(),
            group: $withGroupData ? CategoryGroupData::fromEnum($category->group()) : $category->group()->value,
        );
    }
}

<?php

namespace App\Data\Category;

use App\Enums\Cashflow;
use App\Enums\CategoryGroup;
use App\Objects\Decoration;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One preset category group (the former parent row). */
#[TypeScript]
class CategoryGroupData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public Cashflow $cashflow,
        public Decoration $decorations,
    ) {}

    public static function fromEnum(CategoryGroup $group): self
    {
        return new self(
            id: $group->value,
            name: $group->label(),
            cashflow: $group->flow(),
            decorations: $group->decorations(),
        );
    }
}

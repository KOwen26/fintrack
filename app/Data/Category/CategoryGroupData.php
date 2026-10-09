<?php

namespace App\Data\Category;

use App\Data\DecorationData;
use App\Enums\CategoryGroup;
use App\Enums\CategoryType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One preset category group (the former parent row). */
#[TypeScript]
class CategoryGroupData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public DecorationData $decorations,
        public CategoryType $type,
    ) {}

    public static function fromEnum(CategoryGroup $group): self
    {
        return new self(
            id: $group->value,
            name: $group->label(),
            decorations: $group->decorations(),
            type: $group->type(),
        );
    }
}

<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript()]
class HouseholdData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        /** @var HouseholdMemberData[] */
        public readonly array $members,
    ) {}
}

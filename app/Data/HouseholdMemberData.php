<?php

namespace App\Data;

use App\Enums\HouseholdMemberRole;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript()]
class HouseholdMemberData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly int $user_id,
        public readonly string $name,
        public readonly HouseholdMemberRole $role,
        public readonly ?string $joined_at,
    ) {}
}

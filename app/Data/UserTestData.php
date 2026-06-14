<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript()]
class UserTestData extends Data
{
    public function __construct(
        public string $name,
        public int $age,
        public bool $is_married,
        /** @var string[] */
        public array $hobbies,
        /** @var string[] */
        public array $address
    ) {}
}

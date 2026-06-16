<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class CosmeticData extends Data
{
    public function __construct(
        public ?string $icon = null,
        public ?string $color = null,
    ) {}
}

<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class DecorationData extends Data
{
    public function __construct(
        public ?string $icon = null,
        public ?string $color = null,
    ) {}
}

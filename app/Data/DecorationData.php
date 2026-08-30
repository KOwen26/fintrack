<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class DecorationData extends Data
{
    public function __construct(
        public string $icon,
        public string $color,
    ) {}
}

<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class DecorationData extends Data
{
    public function __construct(
        public DecorationItemData $icon,
        public DecorationItemData $color,
    ) {}
}

<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class DecorationItemData extends Data
{
    public function __construct(
        public string $id,
        public string $value,
    ) {}
}

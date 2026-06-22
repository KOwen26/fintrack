<?php

namespace App\Data\Report;

use Spatie\LaravelData\Data;

class CategorySpendingItemData extends Data
{
    public function __construct(
        public string $name,
        public string $color,
        public string $icon,
        public float $total,
        public float $percentage,
    ) {}
}

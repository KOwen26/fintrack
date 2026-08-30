<?php

namespace App\Data\Report;

use Spatie\LaravelData\Data;

class ParentSpendingItemData extends Data
{
    public function __construct(
        public int $categoryId,
        public string $name,
        public string $color,
        public string $icon,
        public float $total,
        public float $percentage,
        /** @var ChildSpendingItemData[] */
        public array $children,
    ) {}
}

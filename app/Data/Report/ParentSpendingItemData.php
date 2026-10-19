<?php

namespace App\Data\Report;

use Spatie\LaravelData\Data;

class ParentSpendingItemData extends Data
{
    public function __construct(
        /** The CategoryGroup enum value both parent and child rows share. */
        public readonly string $group,
        public readonly string $name,
        public readonly string $color,
        public readonly string $icon,
        public readonly float $total,
        // Share of the whole window's spending, unlike the children's
        // group-relative share.
        public readonly float $percentage,
        /** @var CategorySpendingItemData[] */
        public readonly array $children,
    ) {}
}

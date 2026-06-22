<?php

namespace App\Data\Report;

use Spatie\LaravelData\Data;

class CategorySpendingReportData extends Data
{
    public function __construct(
        /** @var CategorySpendingItemData[] */
        public array $categories,
        public float $period_total,
        public string $from,
        public string $to,
    ) {}
}

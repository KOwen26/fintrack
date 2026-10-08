<?php

namespace App\Data\Report;

use App\Objects\DatePeriod;
use Spatie\LaravelData\Data;

class CategorySpendingReportData extends Data
{
    public function __construct(
        /** @var ParentSpendingItemData[] */
        public array $categories,
        public float $period_total,
        public string $from,
        public string $to,
    ) {}

    public static function emptyForPeriod(DatePeriod $period): self
    {
        return new self(
            categories: [],
            period_total: 0.0,
            from: $period->startDate(),
            to: $period->endDate(),
        );
    }
}

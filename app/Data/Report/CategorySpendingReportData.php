<?php

namespace App\Data\Report;

use App\Objects\DatePeriod;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

/**
 * Parent-child category spending over a window, plus the window's expense
 * total. One of only two report DTOs that can short-circuit to an empty
 * state (no accounts, or zero spend) — hence `::emptyForPeriod()`; the
 * always-zero-filled reports derive emptiness from their row walk instead.
 */
class CategorySpendingReportData extends Data
{
    public function __construct(
        /** @var ParentSpendingItemData[] */
        public readonly array $categories,
        public readonly float $total_expense,
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public readonly CarbonImmutable $from,
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public readonly CarbonImmutable $to,
    ) {}

    public static function emptyForPeriod(DatePeriod $period): self
    {
        return new self(
            categories: [],
            total_expense: 0.0,
            from: $period->start,
            to: $period->end,
        );
    }
}

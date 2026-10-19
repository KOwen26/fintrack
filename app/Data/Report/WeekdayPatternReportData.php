<?php

namespace App\Data\Report;

use App\Objects\WeekdayCashflow;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

class WeekdayPatternReportData extends Data
{
    public function __construct(
        /** @var WeekdayCashflow[] */
        public readonly array $data,
        // The compared week (Monday through Sunday of the current one).
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public readonly CarbonImmutable $from,
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public readonly CarbonImmutable $to,
        // The baseline window the per-weekday averages came from, carried so
        // the UI can label the comparison honestly ("vs 3-mo avg").
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public readonly CarbonImmutable $baseline_from,
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public readonly CarbonImmutable $baseline_to,
        // This week's expense total so far — the empty-state anchor.
        public readonly float $total_expense,
        // The heaviest weekday of the current scope (highest current, ties
        // resolved to the earlier day) — null when nothing has been spent.
        public readonly ?int $peak_weekday,
        // The weekday running hottest against its baseline (highest positive
        // deviation among days with spending) — null when no day sits above
        // its baseline, since "under average" is not a peak.
        public readonly ?int $peak_deviation_weekday,
    ) {}
}

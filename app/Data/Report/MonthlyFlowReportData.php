<?php

namespace App\Data\Report;

use App\Objects\DailyFlow;
use App\Objects\DatePeriod;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

class MonthlyFlowReportData extends Data
{
    public function __construct(
        /** @var DailyFlow[] */
        public array $data,
        // The bounds are calendar days, not instants — serialized as Y-m-d so
        // consumers never have to strip an offset they never wanted.
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public readonly CarbonImmutable $from,
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public readonly CarbonImmutable $to,
        public readonly float $total_income,
        public readonly float $total_expense,
        public readonly float $total_net,
        // Intensity-scale anchors — the month's maxima, so consumers map days
        // to visual steps without re-aggregating the data client-side.
        public readonly float $max_abs_net,
        public readonly float $max_income,
        public readonly float $max_expense,
    ) {}

    public static function emptyForPeriod(DatePeriod $period): self
    {
        return new self(
            data: [],
            from: $period->start,
            to: $period->end,
            total_income: 0.0,
            total_expense: 0.0,
            total_net: 0.0,
            max_abs_net: 0.0,
            max_income: 0.0,
            max_expense: 0.0,
        );
    }
}

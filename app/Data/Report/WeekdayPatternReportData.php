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
        public array $data,
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public readonly CarbonImmutable $from,
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public readonly CarbonImmutable $to,
        public readonly float $total_expense,
        // The highest-average weekday, ISO 1–7 — null when the window has no
        // expenses at all. Ties resolve to the earlier day.
        public readonly ?int $peak_weekday,
    ) {}
}

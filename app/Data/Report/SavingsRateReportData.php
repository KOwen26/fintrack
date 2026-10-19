<?php

namespace App\Data\Report;

use App\Objects\CashflowPoint;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

class SavingsRateReportData extends Data
{
    public function __construct(
        /** @var CashflowPoint[] */
        public readonly array $data,

        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public readonly CarbonImmutable $from,

        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public readonly CarbonImmutable $to,

        // Target line rendered on the history chart (percent).
        public readonly float $target_rate,

        // Current (latest) month's rate.
        public readonly float $current_rate,

        // Arithmetic mean of the window's monthly rates.
        public readonly float $average_rate,

        // Consecutive healthy months ending at the latest month.
        public readonly int $current_streak,

        // Longest run of consecutive healthy months anywhere in the window.
        public readonly int $longest_streak,

        // Best and worst single-month rates in the window.
        public readonly float $best_rate,

        public readonly float $worst_rate,
    ) {}
}

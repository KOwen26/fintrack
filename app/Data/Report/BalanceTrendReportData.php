<?php

namespace App\Data\Report;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

class BalanceTrendReportData extends Data
{
    public function __construct(
        /**
         * Month-end readings, oldest → newest. Rows are plain shapes rather
         * than a value object — a stock reading carries no derived behavior,
         * and the dates are pre-formatted Y-m-d to keep consumers free of
         * UTC-midnight parsing traps.
         *
         * @var array<int, array{date: string, balance: float, net_delta: float}>
         */
        public array $data,
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public readonly CarbonImmutable $from,
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public readonly CarbonImmutable $to,
        // The anchor the backward walk started from — the accounts' current
        // balance total, so the series provably agrees with the dashboard.
        public readonly float $current_total,
    ) {}
}

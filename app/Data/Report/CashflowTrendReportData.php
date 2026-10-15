<?php

namespace App\Data\Report;

use App\Objects\MonthlyCashflow;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

class CashflowTrendReportData extends Data
{
    public function __construct(
        /** @var MonthlyCashflow[] */
        public array $data,
        // The bounds are calendar months, not instants — serialized as Y-m-d so
        // consumers never have to strip an offset they never wanted.
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public readonly CarbonImmutable $from,
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public readonly CarbonImmutable $to,
        public readonly float $total_income,
        public readonly float $total_expense,
        public readonly float $total_net,
    ) {}
}

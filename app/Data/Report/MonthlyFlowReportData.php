<?php

namespace App\Data\Report;

use App\Objects\DailyFlow;
use App\Objects\DatePeriod;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

class MonthlyFlowReportData extends Data
{
    public function __construct(
        /** @var DailyFlow[] */
        public array $data,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
    ) {}

    public static function emptyForPeriod(DatePeriod $period): self
    {
        return new self(
            data: [],
            from: $period->start,
            to: $period->end,
        );
    }
}

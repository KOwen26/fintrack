<?php

use App\Enums\DatePeriodPreset;
use Carbon\CarbonImmutable;

it('resolves multi-year rolling windows anchored at the given moment', function (DatePeriodPreset $preset, string $expectedStart): void {
    $period = $preset->toPeriod(new CarbonImmutable('2026-10-15 14:00'));

    expect($period->startDate())->toBe($expectedStart)
        ->and($period->endDate())->toBe('2026-10-15');
})->with([
    'last 24 months' => [DatePeriodPreset::Last24Months, '2024-10-15'],
    'last 3 years' => [DatePeriodPreset::Last3Years, '2023-10-15'],
    'last 5 years' => [DatePeriodPreset::Last5Years, '2021-10-15'],
]);

it('reports rolling month counts only for month-granular rolling windows', function (DatePeriodPreset $preset, ?int $months): void {
    expect($preset->rollingMonths())->toBe($months);
})->with([
    'last 3 months' => [DatePeriodPreset::Last3Months, 3],
    'last 6 months' => [DatePeriodPreset::Last6Months, 6],
    'last 12 months' => [DatePeriodPreset::Last12Months, 12],
    'year on year' => [DatePeriodPreset::YearOnYear, 12],
    'last 24 months' => [DatePeriodPreset::Last24Months, 24],
    'last 3 years' => [DatePeriodPreset::Last3Years, 36],
    'last 5 years' => [DatePeriodPreset::Last5Years, 60],
    'this month' => [DatePeriodPreset::ThisMonth, null],
    'last 30 days' => [DatePeriodPreset::Last30Days, null],
    'all' => [DatePeriodPreset::All, null],
]);

<?php

use App\Objects\DatePeriod;
use Carbon\CarbonImmutable;
use Tests\TestCase;

// Plain PHPUnit binding under tests/Unit — opt in for travelTo().
pest()->extend(TestCase::class);

it('caps the period at today when no end is given', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-13 14:00'));

    $period = new DatePeriod(CarbonImmutable::parse('2026-01-05'));

    expect($period->end->toDateTimeString())->toBe('2026-10-13 23:59:59')
        ->and($period->endDate())->toBe('2026-10-13');
});

it('keeps an explicitly given end', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-13 14:00'));

    $period = new DatePeriod(
        CarbonImmutable::parse('2026-01-05'),
        CarbonImmutable::parse('2026-03-09 09:30'),
    );

    expect($period->endDate())->toBe('2026-03-09');
});

it('rejects an end before the start', function (): void {
    expect(fn (): DatePeriod => new DatePeriod(
        CarbonImmutable::parse('2026-03-09'),
        CarbonImmutable::parse('2026-01-05'),
    ))->toThrow(InvalidArgumentException::class, 'DatePeriod end must not be before its start.');
});

it('rejects a start after today when the end defaults to today', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-13 14:00'));

    expect(fn (): DatePeriod => new DatePeriod(CarbonImmutable::parse('2026-10-14')))
        ->toThrow(InvalidArgumentException::class, 'DatePeriod end must not be before its start.');
});

it('widens bounds to the full boundary days for SQL comparison', function (): void {
    $period = new DatePeriod(
        CarbonImmutable::parse('2026-01-05 14:00'),
        CarbonImmutable::parse('2026-03-09 09:30'),
    );

    expect($period->toRange())->toBe(['2026-01-05 00:00:00', '2026-03-09 23:59:59'])
        ->and($period->startDate())->toBe('2026-01-05')
        ->and($period->startDate(asDateTime: true))->toBe('2026-01-05 00:00:00')
        ->and($period->endDate())->toBe('2026-03-09')
        ->and($period->endDate(asDateTime: true))->toBe('2026-03-09 23:59:59');
});

it('keeps the widest end for unbounded periods', function (): void {
    $period = DatePeriod::unbounded();

    expect($period->endDate())->toBe('9999-12-31')
        ->and($period->toRange()[1])->toBe('9999-12-31 23:59:59');
});

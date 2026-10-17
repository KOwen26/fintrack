<?php

namespace App\Enums;

use App\Objects\DatePeriod;
use Carbon\CarbonImmutable;
use Carbon\WeekDay;
use DateTimeInterface;

/**
 * Contextual period presets resolved server-side, intended as the wire format
 * for `?period=` query filtering.
 */
enum DatePeriodPreset: string
{
    /**
     * Week boundaries are explicit rather than locale-inferred so
     * `this-week`/`last-week` resolve identically everywhere.
     */
    private const WeekStartsAt = WeekDay::Monday;

    private const WeekEndsAt = WeekDay::Sunday;

    case Today = 'today';
    case Yesterday = 'yesterday';

    case ThisWeek = 'this_week';
    case ThisMonth = 'this_month';
    case ThisYear = 'this_year';

    case Last7Days = 'last_7days';
    case Last14Days = 'last_14days';
    case Last30Days = 'last_30days';
    case LastMonth = 'last_month';
    case Last3Months = 'last_3months';
    case Last6Months = 'last_6months';
    case YearOnYear = 'year_on_year';
    case Last12Months = 'last_12months';
    case Last24Months = 'last_24months';
    case Last3Years = 'last_3years';
    case Last5Years = 'last_5years';
    case LastYear = 'last_year';

    case All = 'all';

    public function label(): string
    {
        return match ($this) {
            self::Today => 'Today',
            self::Yesterday => 'Yesterday',
            self::ThisWeek => 'This Week',
            self::ThisMonth => 'This Month',
            self::ThisYear => 'This Year',
            self::Last7Days => 'Last 7 Days',
            self::Last14Days => 'Last 14 Days',
            self::Last30Days => 'Last 30 Days',
            self::LastMonth => 'Last Month',
            self::Last3Months => 'Last 3 Months',
            self::Last6Months => 'Last 6 Months',
            self::YearOnYear => 'Year on Year',
            self::Last12Months => 'Last 12 Months',
            self::Last24Months => 'Last 24 Months',
            self::Last3Years => 'Last 3 Years',
            self::Last5Years => 'Last 5 Years',
            self::LastYear => 'Last Year',
            self::All => 'All Time',
        };
    }

    public function labelShort(): string
    {
        return match ($this) {
            self::Today => 'Today',
            self::Yesterday => 'Yesterday',
            self::ThisWeek => 'This Week',
            self::ThisMonth => '1M',
            self::ThisYear => '1Y',
            self::Last7Days => '7D',
            self::Last14Days => '14D',
            self::Last30Days => '30D',
            self::LastMonth => 'Last Month',
            self::Last3Months => '3M',
            self::Last6Months => '6M',
            self::YearOnYear => 'YoY',
            self::Last12Months => '12M',
            self::Last24Months => '24M',
            self::Last3Years => '3Y',
            self::Last5Years => '5Y',
            self::LastYear => 'Last Year',
            self::All => 'All',
        };
    }

    /**
     * Resolve the preset into a concrete period anchored at the given moment
     * (defaults to now) so tests can freeze time and future callers can pin a
     * shared reference point.
     */
    public function toPeriod(?DateTimeInterface $anchor = null): DatePeriod
    {
        $now = CarbonImmutable::instance($anchor ?? now());

        return match ($this) {
            self::Today => DatePeriod::day($now),

            self::Yesterday => DatePeriod::day($now->subDay()),

            // Calendar windows snap to period boundaries.
            self::ThisWeek => new DatePeriod($now->startOfWeek(self::WeekStartsAt), $now->endOfWeek(self::WeekEndsAt)),

            self::ThisMonth => new DatePeriod($now->startOfMonth(), $now->endOfMonth()),

            self::ThisYear => new DatePeriod($now->startOfYear(), $now->endOfYear()),

            self::Last7Days => new DatePeriod($now->subDays(6)->startOfDay(), $now->endOfDay()),

            self::Last14Days => new DatePeriod($now->subDays(13)->startOfDay(), $now->endOfDay()),

            self::Last30Days => new DatePeriod($now->subDays(29)->startOfDay(), $now->endOfDay()),

            self::Last3Months => new DatePeriod($now->subMonthsNoOverflow(3)->startOfDay(), $now->endOfDay()),

            self::Last6Months => new DatePeriod($now->subMonthsNoOverflow(6)->startOfDay(), $now->endOfDay()),

            self::YearOnYear, self::Last12Months => new DatePeriod($now->subMonthsNoOverflow(12)->startOfDay(), $now->endOfDay()),

            self::Last24Months => new DatePeriod($now->subMonthsNoOverflow(24)->startOfDay(), $now->endOfDay()),

            self::Last3Years => new DatePeriod($now->subYears(3)->startOfDay(), $now->endOfDay()),

            self::Last5Years => new DatePeriod($now->subYears(5)->startOfDay(), $now->endOfDay()),

            self::LastMonth => new DatePeriod($now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()),

            self::LastYear => new DatePeriod($now->subYear()->startOfYear(), $now->subYear()->endOfYear()),

            self::All => DatePeriod::unbounded(),
        };
    }

    /**
     * The rolling month count for month-granular rolling windows, or null
     * for day/week windows, calendar windows, and unbounded All. Shared by
     * the trend reports, which both walk whole months backward from now.
     */
    public function rollingMonths(): ?int
    {
        return match ($this) {
            self::Last3Months => 3,
            self::Last6Months => 6,
            self::YearOnYear, self::Last12Months => 12,
            self::Last24Months => 24,
            self::Last3Years => 36,
            self::Last5Years => 60,
            default => null,
        };
    }
}

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
    case LastYear = 'last_year';

    case All = 'all';

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

            self::LastMonth => new DatePeriod($now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()),

            self::LastYear => new DatePeriod($now->subYear()->startOfYear(), $now->subYear()->endOfYear()),

            self::All => DatePeriod::unbounded(),
        };
    }
}

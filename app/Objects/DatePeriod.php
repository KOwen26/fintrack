<?php

namespace App\Objects;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Immutable, day-precision date range for transaction period filtering.
 *
 * Omitting the end caps the period at today — transactions are never
 * future-dated, so an end beyond today can never match more rows. The widest
 * end a SQL `date` column can express (year 9999) is reserved for unbounded().
 */
final readonly class DatePeriod
{
    public CarbonImmutable $start;

    public CarbonImmutable $end;

    public function __construct(DateTimeInterface $start, ?DateTimeInterface $end = null)
    {
        $this->start = CarbonImmutable::instance($start);
        $this->end = $end === null
            ? CarbonImmutable::today()->endOfDay()
            : CarbonImmutable::instance($end);

        if ($this->end->lessThan($this->start)) {
            throw new InvalidArgumentException('DatePeriod end must not be before its start.');
        }
    }

    /** A single calendar day. */
    public static function day(DateTimeInterface $day): self
    {
        $day = CarbonImmutable::instance($day)->startOfDay();

        return new self($day, $day->endOfDay());
    }

    /** The calendar month containing the given day. */
    public static function forMonth(DateTimeInterface $day): self
    {
        $start = CarbonImmutable::instance($day)->startOfMonth();

        return new self($start, $start->endOfMonth());
    }

    /**
     * Whole calendar months ending with the anchor's month — the final month
     * may be partial (the current one during live use), by rolling-span
     * design rather than accident.
     */
    public static function forRollingMonths(int $months, ?DateTimeInterface $anchor = null): self
    {
        $end = CarbonImmutable::instance($anchor ?? now());

        return new self(
            $end->subMonthsNoOverflow($months - 1)->startOfMonth(),
            $end->endOfMonth(),
        );
    }

    /** The widest period a MySQL `date` column can reliably express. */
    public static function unbounded(): self
    {
        return new self(
            CarbonImmutable::create(2000, 1, 1)->startOfDay(),
            CarbonImmutable::create(9999, 12, 31)->endOfDay(),
        );
    }

    /**
     * Inclusive lower bound. Defaults to the day (`Y-m-d`); `asDateTime` widens
     * to the full day (`Y-m-d 00:00:00`) for SQL comparison against the
     * datetime-formatted strings models store.
     */
    public function startDate(bool $asDateTime = false): string
    {
        return $asDateTime
            ? $this->start->startOfDay()->format('Y-m-d H:i:s')
            : $this->start->toDateString();
    }

    /**
     * Inclusive upper bound. Defaults to the day (`Y-m-d`); `asDateTime` widens
     * to the full day (`Y-m-d 23:59:59`) so boundary-day rows are never dropped
     * by a lexicographic sqlite comparison.
     */
    public function endDate(bool $asDateTime = false): string
    {
        return $asDateTime
            ? $this->end->endOfDay()->format('Y-m-d H:i:s')
            : $this->end->toDateString();
    }

    /**
     * Inclusive bounds for SQL comparison, in datetime form. Models store
     * `transaction_date` in the connection's `Y-m-d H:i:s` format even on
     * `date` columns, so date-only strings would mis-compare on sqlite
     * (lexicographic) and drop boundary-day rows.
     *
     * @return array{0: string, 1: string}
     */
    public function toRange(): array
    {
        return [$this->startDate(asDateTime: true), $this->endDate(asDateTime: true)];
    }
}

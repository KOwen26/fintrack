<?php

namespace App\Objects;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Immutable, day-precision date range for transaction period filtering.
 *
 * A null end represents an open-ended period ("since start"); boundary helpers
 * report the widest date a SQL `date` column can express so callers never need
 * null handling in queries or display DTOs.
 */
final readonly class DatePeriod
{
    public CarbonImmutable $start;

    public ?CarbonImmutable $end;

    public function __construct(DateTimeInterface $start, ?DateTimeInterface $end = null)
    {
        $this->start = CarbonImmutable::instance($start);
        $this->end = $end === null ? null : CarbonImmutable::instance($end);

        if ($this->end !== null && $this->end->lessThan($this->start)) {
            throw new InvalidArgumentException('DatePeriod end must not be before its start.');
        }
    }

    /** A single calendar day. */
    public static function day(DateTimeInterface $day): self
    {
        $day = CarbonImmutable::instance($day)->startOfDay();

        return new self($day, $day->endOfDay());
    }

    /** The widest period a MySQL `date` column can reliably express. */
    public static function unbounded(): self
    {
        return new self(
            CarbonImmutable::create(2000, 1, 1)->startOfDay(),
            CarbonImmutable::create(9999, 12, 31)->endOfDay(),
        );
    }

    /** Inclusive lower bound formatted for `date` comparison. */
    public function startDate(): string
    {
        return $this->start->toDateString();
    }

    /** Inclusive upper bound formatted for `date` comparison. */
    public function endDate(): string
    {
        return ($this->end ?? CarbonImmutable::create(9999, 12, 31))->toDateString();
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function toRange(): array
    {
        return [$this->startDate(), $this->endDate()];
    }
}

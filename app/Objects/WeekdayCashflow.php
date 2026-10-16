<?php

namespace App\Objects;

use App\Enums\StatisticDataTier;
use Illuminate\Contracts\Support\Arrayable;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One weekday's spending this week against its trailing-window baseline —
 * the comparison grain of the cashflow family. `deviation` (percent change
 * vs the baseline) is derived once at construction; `tier` is assigned by
 * the report that builds the full set, since rank only exists across rows.
 * All seven rows are always present in a report, zero-filled; days later in
 * the week than today carry current = 0 — week-to-date, not frugality.
 */
#[TypeScript]
final readonly class WeekdayCashflow implements Arrayable
{
    public readonly float $deviation;

    public function __construct(
        /** ISO-8601 weekday: 1 (Monday) through 7 (Sunday). */
        public readonly int $weekday,
        public readonly float $current = 0.0,
        public readonly float $baseline = 0.0,
        public readonly StatisticDataTier $tier = StatisticDataTier::Rest,
    ) {
        // A zero baseline has no fair percent — 0.0, matching the report
        // family's zero-data convention (see surplus_rate).
        $this->deviation = $this->baseline > 0
            ? round(($this->current - $this->baseline) / $this->baseline * 100, 2)
            : 0.0;
    }

    /** @return array{weekday: int, current: float, baseline: float, deviation: float, tier: string} */
    public function toArray(): array
    {
        return [
            'weekday' => $this->weekday,
            'current' => $this->current,
            'baseline' => $this->baseline,
            'deviation' => $this->deviation,
            'tier' => $this->tier->value,
        ];
    }
}

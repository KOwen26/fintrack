<?php

namespace App\Objects;

use Illuminate\Contracts\Support\Arrayable;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One weekday's expense rhythm across a reporting window — the day-of-week
 * grain of the cashflow family. `average` and `share` are derived once at
 * construction (the caller supplies the window context they depend on) so
 * consumers and the generated TypeScript type can never disagree with the
 * math. All seven rows are always present in a report, zero-filled.
 */
#[TypeScript]
final readonly class WeekdayCashflow implements Arrayable
{
    public readonly float $average;

    public readonly float $share;

    public function __construct(
        /** ISO-8601 weekday: 1 (Monday) through 7 (Sunday). */
        public readonly int $weekday,
        public readonly float $expense = 0.0,
        public readonly int $count = 0,
        int $occurrences = 1,
        float $windowTotal = 0.0,
    ) {
        // Averaging base is how often the weekday occurs in the window —
        // months hold four or five of each, so raw totals compare unfairly.
        $this->average = round($this->expense / max($occurrences, 1), 2);

        $this->share = $windowTotal > 0
            ? round($this->expense / $windowTotal * 100, 2)
            : 0.0;
    }

    /** @return array{weekday: int, expense: float, average: float, share: float, count: int} */
    public function toArray(): array
    {
        return [
            'weekday' => $this->weekday,
            'expense' => $this->expense,
            'average' => $this->average,
            'share' => $this->share,
            'count' => $this->count,
        ];
    }
}

<?php

namespace App\Objects;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One calendar day's income/expense flow across a set of accounts.
 *
 * Days without activity are simply absent from a report's data rather than
 * zero-filled — the calendar grid synthesizes empty days on the frontend, so
 * the wire stays proportional to activity. `net` is derived once at
 * construction so consumers (and the generated TypeScript type) can never
 * disagree with the subtraction.
 */
#[TypeScript]
final readonly class DailyCashflow implements Arrayable
{
    public readonly CarbonImmutable $date;

    public readonly float $net;

    public function __construct(
        DateTimeInterface $date,
        public readonly float $income = 0,
        public readonly float $expense = 0,
    ) {
        $this->date = CarbonImmutable::instance($date)->startOfDay();
        $this->net = $this->income - $this->expense;
    }

    /** @return array{date: string, income: float, expense: float, net: float} */
    public function toArray(): array
    {
        return [
            'date' => $this->date->toDateString(),
            'income' => $this->income,
            'expense' => $this->expense,
            'net' => $this->net,
        ];
    }
}

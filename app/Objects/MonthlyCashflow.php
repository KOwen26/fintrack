<?php

namespace App\Objects;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One calendar month's income/expense flow across a set of accounts — the
 * monthly grain of DailyCashflow, zero-filled by the report that builds it
 * so trend axes are continuous. `net` and `surplusRate` are derived once at
 * construction so consumers (and the generated TypeScript type) can never
 * disagree with the math.
 */
#[TypeScript]
final readonly class MonthlyCashflow implements Arrayable
{
    public readonly CarbonImmutable $date;

    public readonly float $net;

    // Snake-named to match its wire key — the generated TypeScript type is
    // reflected from the property, and the DTO family is snake on the wire.
    public readonly float $surplus_rate;

    public function __construct(
        DateTimeInterface $date,
        public readonly float $income = 0,
        public readonly float $expense = 0,
    ) {
        $this->date = CarbonImmutable::instance($date)->startOfMonth();
        $this->net = $this->income - $this->expense;
        $this->surplus_rate = $this->income > 0
            ? round($this->net / $this->income * 100, 2)
            : 0.0;
    }

    /** @return array{date: string, income: float, expense: float, net: float, surplus_rate: float} */
    public function toArray(): array
    {
        return [
            'date' => $this->date->toDateString(),
            'income' => $this->income,
            'expense' => $this->expense,
            'net' => $this->net,
            'surplus_rate' => $this->surplus_rate,
        ];
    }
}

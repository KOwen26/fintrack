<?php

namespace App\Objects;

use App\Enums\SavingsRateTier;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One calendar slot's income/expense flow across a set of accounts — the
 * single flow point for every report grain. Named against the
 * `App\Enums\Cashflow` direction so the two never read as one thing: the
 * enum is a transaction's direction, this object is one reading on a
 * series. The date's precision is the grain: a day for the monthly
 * cashflow calendar, a month for the trend and savings-rate reports;
 * callers pass an already-normalized date, the object never re-rounds it.
 *
 * `net` and `rate` are derived once at construction so consumers (and the
 * generated TypeScript type) can never disagree with the math. `rate` is
 * the net/income share in percent — formerly two same-formula fields under
 * two names (`surplus_rate`, `savings_rate`).
 *
 * Banding is optional and assigned by the report that banded (`::banded()`);
 * pure cashflow reports leave `tier` null. Tier assignment happens in
 * report builders, never as a side effect of construction — see
 * WeekdayCashflow for the comparison grain.
 */
#[TypeScript]
final readonly class CashflowPoint implements Arrayable
{
    public readonly CarbonImmutable $date;

    public readonly float $net;

    public readonly float $rate;

    public function __construct(
        DateTimeInterface $date,
        public readonly float $income = 0,
        public readonly float $expense = 0,
        public readonly ?SavingsRateTier $tier = null,
    ) {
        $this->date = CarbonImmutable::instance($date);
        $this->net = $this->income - $this->expense;
        $this->rate = $this->income > 0
            ? round($this->net / $this->income * 100, 2)
            : 0.0;
    }

    /** An empty slot for zero-filling axes — no flow, no banding. */
    public static function zero(DateTimeInterface $date): self
    {
        return new self($date);
    }

    /**
     * A flow banded by its derived rate — the savings-rate report's
     * presentation layer.
     */
    public static function banded(DateTimeInterface $date, float $income = 0, float $expense = 0): self
    {
        return new self($date, $income, $expense, SavingsRateTier::fromRate((new self($date, $income, $expense))->rate));
    }

    /** @return array{date: string, income: float, expense: float, net: float, rate: float, tier: string|null} */
    public function toArray(): array
    {
        return [
            'date' => $this->date->toDateString(),
            'income' => $this->income,
            'expense' => $this->expense,
            'net' => $this->net,
            'rate' => $this->rate,
            'tier' => $this->tier?->value,
        ];
    }
}

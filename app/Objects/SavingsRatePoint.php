<?php

namespace App\Objects;

use App\Enums\SavingsRateTier;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One month's savings-rate reading. Only percentages and counts — never
 * amounts — so the chart reads as a performance indicator.
 */
#[TypeScript]
final readonly class SavingsRatePoint implements Arrayable
{
    public readonly CarbonImmutable $date;

    public readonly float $savings_rate;

    public readonly string $tier;

    public function __construct(
        DateTimeInterface $date,
        public readonly float $income = 0,
        public readonly float $expense = 0,
    ) {
        $this->date = CarbonImmutable::instance($date)->startOfMonth();
        $net = $this->income - $this->expense;
        $this->savings_rate = $this->income > 0
            ? round($net / $this->income * 100, 2)
            : 0.0;
        $this->tier = SavingsRateTier::fromRate($this->savings_rate)->value;
    }

    /** @return array{date: string, income: float, expense: float, savings_rate: float, tier: string} */
    public function toArray(): array
    {
        return [
            'date' => $this->date->toDateString(),
            'income' => $this->income,
            'expense' => $this->expense,
            'savings_rate' => $this->savings_rate,
            'tier' => $this->tier,
        ];
    }
}

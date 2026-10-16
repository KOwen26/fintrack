<?php

namespace App\Http\Requests;

use App\Enums\DatePeriodPreset;
use Illuminate\Contracts\Validation\Validator;

/**
 * The statistics page request — the month window plus the cashflow trend
 * card's period preset. `?trend` is normalized in the validator's after
 * hook rather than rules: anything outside the enum falls back to the
 * default silently, mirroring `?month`'s lenient handling — the card never
 * 422s on a hand-edited URL.
 */
final class StatisticsIndexRequest extends MonthPeriodRequest
{
    private const DatePeriodPreset DefaultTrendPreset = DatePeriodPreset::Last6Months;

    /** @var list<int> */
    private const array BalanceSpans = [6, 12, 24];

    private const int DefaultBalanceSpan = 12;

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (): void {
            $preset = DatePeriodPreset::tryFrom((string) $this->query('trend', '')) ?? self::DefaultTrendPreset;

            $this->merge([
                'trend' => $preset->value,
                'balance' => $this->normalizeSpan('balance', self::BalanceSpans, self::DefaultBalanceSpan),
            ]);
        });
    }

    /** The cashflow trend window — `?trend` as a DatePeriodPreset or the default. */
    public function trendPreset(): DatePeriodPreset
    {
        return DatePeriodPreset::from((string) $this->input('trend'));
    }

    /** The balance trend span in months — `?balance` or the default. */
    public function balanceSpan(): int
    {
        return $this->integer('balance');
    }

    /**
     * Pass a span through when it is one of the allowed values, otherwise
     * fall back to the default — absent and garbage input behave the same.
     *
     * @param  list<int>  $allowed
     */
    private function normalizeSpan(string $key, array $allowed, int $default): int
    {
        $span = (int) $this->query($key, (string) $default);

        return in_array($span, $allowed, true) ? $span : $default;
    }
}

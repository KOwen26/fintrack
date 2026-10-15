<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;

/**
 * The statistics page request — the month window plus the trend cards'
 * rolling spans. Spans are normalized in the validator's after hook rather
 * than rules: anything outside the allowed sets defaults silently, mirroring
 * `?month`'s lenient handling — the cards never 422 on a hand-edited URL.
 */
final class StatisticsIndexRequest extends MonthPeriodRequest
{
    /** @var list<int> */
    private const array TrendSpans = [3, 6, 12];

    /** @var list<int> */
    private const array BalanceSpans = [6, 12, 24];

    private const int DefaultTrendSpan = 6;

    private const int DefaultBalanceSpan = 12;

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (): void {
            $this->merge([
                'trend' => $this->normalizeSpan('trend', self::TrendSpans, self::DefaultTrendSpan),
                'balance' => $this->normalizeSpan('balance', self::BalanceSpans, self::DefaultBalanceSpan),
            ]);
        });
    }

    /** The income/expense trend span in months — `?trend` or the default. */
    public function trendSpan(): int
    {
        return $this->integer('trend');
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

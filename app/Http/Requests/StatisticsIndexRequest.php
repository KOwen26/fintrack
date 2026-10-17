<?php

namespace App\Http\Requests;

use App\Enums\DatePeriodPreset;
use Illuminate\Contracts\Validation\Validator;

/**
 * The statistics page request — the month window plus the trend cards'
 * DatePeriodPresets. `?trend` and `?balance` are normalized in the
 * validator's after hook rather than rules: anything outside the enum falls
 * back to the default silently, mirroring `?month`'s lenient handling — the
 * cards never 422 on a hand-edited URL.
 */
final class StatisticsIndexRequest extends MonthPeriodRequest
{
    private const DatePeriodPreset DefaultTrendPreset = DatePeriodPreset::Last6Months;

    private const DatePeriodPreset DefaultBalancePreset = DatePeriodPreset::Last6Months;

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (): void {
            $trend = DatePeriodPreset::tryFrom((string) $this->query('trend', '')) ?? self::DefaultTrendPreset;
            $balance = DatePeriodPreset::tryFrom((string) $this->query('balance', '')) ?? self::DefaultBalancePreset;

            $this->merge([
                'trend' => $trend->value,
                'balance' => $balance->value,
            ]);
        });
    }

    /** The cashflow trend window — `?trend` as a DatePeriodPreset or the default. */
    public function trendPreset(): DatePeriodPreset
    {
        return DatePeriodPreset::from((string) $this->input('trend'));
    }

    /** The balance trend window — `?balance` as a DatePeriodPreset or the default. */
    public function balancePreset(): DatePeriodPreset
    {
        return DatePeriodPreset::from((string) $this->input('balance'));
    }
}

<?php

namespace App\Http\Requests;

use App\Objects\DatePeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;

/**
 * Shared request for month-navigable report endpoints — resolves `?month`
 * into the DatePeriod to report on. The anchor is canonicalized to Y-m-d
 * before validation (a Y-m month anchors to its first day); anything
 * malformed is dropped rather than rejected, so the window defaults to the
 * current month — lenient by design, hand-edited URLs degrade gracefully.
 */
final class MonthPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('month')) {
            return;
        }

        $month = (string) $this->query('month');

        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1) {
            $this->merge(['month' => "{$month}-01"]);

            return;
        }

        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $month) !== 1) {
            $this->merge(['month' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'month' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /** The month window to report on — the `?month` anchor or the current month. */
    public function period(): DatePeriod
    {
        $month = $this->validated('month');

        return DatePeriod::forMonth($month ? Date::parse($month) : now());
    }
}

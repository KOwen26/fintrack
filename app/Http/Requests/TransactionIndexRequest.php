<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Read-filter validation for GET /transactions. The payload is the frontend
 * table's state structure (TanStack-shaped): a global filter string, a list
 * of column filters, and a sort descriptor. Unknown column ids or sort
 * columns fail loudly (422) — the only client is our own UI, and silent
 * filtering would hide bugs.
 */
class TransactionIndexRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'global_filter' => ['nullable', 'string', 'max:100'],
            'filters' => ['nullable', 'array', 'max:10'],
            'filters.*.id' => ['required', 'string', Rule::in(['account_id', 'category_id', 'type', 'tags'])],
            'filters.*.value' => ['required', 'array', 'min:1'],
            'filters.*.value.*' => ['integer|string'],
            'sorting' => ['nullable', 'array', 'max:1'],
            'sorting.0.id' => ['required_with:sorting', 'string', Rule::in(['transaction_date', 'amount'])],
            'sorting.0.desc' => ['required_with:sorting', 'boolean'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Enums\RecurringFrequency;
use App\Enums\TransactionPresetType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecurringPresetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'type' => [
                'required',
                'string',
                Rule::in([TransactionPresetType::Income->value, TransactionPresetType::Expense->value]),
            ],
            'frequency' => ['required', 'string', Rule::enum(RecurringFrequency::class)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
            'next_run_date' => ['required', 'date', 'after_or_equal:today'],
            'recurrence_end_date' => ['nullable', 'date', 'after:next_run_date'],
        ];
    }
}

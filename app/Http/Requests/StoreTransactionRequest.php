<?php

namespace App\Http\Requests;

use App\Enums\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $typeValues = array_merge(
            array_column(TransactionType::cases(), 'value'),
            ['transfer']
        );

        return [
            'type' => ['required', 'string', Rule::in($typeValues)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'destination_account_id' => [
                Rule::requiredIf(fn () => $this->input('type') === 'transfer'),
                'nullable',
                'integer',
                'exists:accounts,id',
                'different:account_id',
            ],
            'fee_amount' => ['nullable', 'numeric', 'min:0.01'],
        ];
    }
}

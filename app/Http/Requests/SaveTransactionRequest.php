<?php

namespace App\Http\Requests;

use App\Enums\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Plain-row validation for POST/PUT /transactions. Transfer shapes
 * (destination, fee) are rejected outright — they belong to
 * SaveTransferRequest via the /transfers endpoints.
 */
class SaveTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'type' => ['required', 'string', Rule::in([TransactionType::Income->value, TransactionType::Expense->value])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'destination_account_id' => ['prohibited'],
            'fee_amount' => ['prohibited'],
        ];
    }
}

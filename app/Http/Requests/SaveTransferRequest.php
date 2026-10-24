<?php

namespace App\Http\Requests;

use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Transfer-unit validation for POST/PUT /transfers. Unconditional rules —
 * the endpoint implies the transfer type; no type field is accepted.
 */
final class SaveTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('transfer') !== null
            ? $this->user()->can('update', $this->route('transfer'))
            : $this->user()->can('create', Transaction::class);
    }

    public function rules(): array
    {
        return [
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'destination_account_id' => ['required', 'integer', 'exists:accounts,id', 'different:account_id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'fee_amount' => ['nullable', 'numeric', 'min:0.01'],
            'transaction_date' => ['required', 'date', 'before_or_equal:now'],
            'description' => ['nullable', 'string', 'max:500'],
            'type' => ['prohibited'],
            'category_id' => ['prohibited'],
        ];
    }
}

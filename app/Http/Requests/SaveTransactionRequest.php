<?php

namespace App\Http\Requests;

use App\Enums\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Combined request for creating and updating a transaction. Both flows share
 * the same payload shape; the controller decides the authorization and the
 * service decides which fields a given flow may persist.
 */
class SaveTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // 'transfer' is a UI pseudo-type: the service expands it into the
        // TransferOut/TransferIn pair (plus optional fee).
        $typeValues = array_merge(
            array_column(TransactionType::cases(), 'value'),
            ['transfer']
        );

        return [
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'type' => ['required', 'string', Rule::in($typeValues)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            // Income/expense always book under a category; transfers don't carry one.
            'category_id' => [
                Rule::requiredIf(fn (): bool => in_array($this->input('type'), ['income', 'expense'])),
                'nullable',
                'integer',
                'exists:categories,id',
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'destination_account_id' => [
                Rule::requiredIf(fn (): bool => $this->input('type') === 'transfer'),
                'nullable',
                'integer',
                'exists:accounts,id',
                'different:account_id',
            ],
            'fee_amount' => ['nullable', 'numeric', 'min:0.01'],
        ];
    }
}

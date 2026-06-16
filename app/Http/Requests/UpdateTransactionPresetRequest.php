<?php

namespace App\Http\Requests;

use App\Enums\TransactionPresetType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransactionPresetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::enum(TransactionPresetType::class)],
            'default_amount' => ['nullable', 'numeric', 'min:0'],
            'default_description' => ['nullable', 'string', 'max:255'],
            'default_category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'default_source_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'default_destination_account_id' => [
                'nullable',
                'integer',
                'exists:accounts,id',
                Rule::requiredIf(fn () => $this->input('type') === TransactionPresetType::Transfer->value),
            ],
            'default_transfer_fee' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}

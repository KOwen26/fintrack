<?php

namespace App\Http\Requests;

use App\Enums\Category;
use App\Enums\TransactionType;
use App\Models\ImportRow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateImportRowRequest extends FormRequest
{
    public function authorize(): bool
    {
        $row = $this->route('row');

        return $row instanceof ImportRow && $this->user()->can('update', $row->batch);
    }

    public function rules(): array
    {
        return [
            'transaction_date' => ['sometimes', 'required', 'date', 'before_or_equal:now'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'transaction_type' => ['sometimes', 'required', Rule::enum(TransactionType::class)],
            'category_id' => ['sometimes', 'required', Rule::enum(Category::class)->except(Category::InitialBalance)],
            'transfer_destination_account_id' => ['sometimes', 'nullable', 'integer', 'exists:accounts,id'],
            'is_included' => ['sometimes', 'boolean'],
        ];
    }
}

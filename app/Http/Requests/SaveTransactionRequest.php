<?php

namespace App\Http\Requests;

use App\Enums\Category;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Services\TagService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Plain-row validation for POST/PUT /transactions. Transfer shapes
 * (destination, fee) are rejected outright — they belong to
 * SaveTransferRequest via the /transfers endpoints.
 */
final class SaveTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('transaction') !== null
            ? $this->user()->can('update', $this->route('transaction'))
            : $this->user()->can('create', Transaction::class);
    }

    public function rules(): array
    {
        return [
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'type' => ['required', 'string', Rule::in([TransactionType::Income->value, TransactionType::Expense->value])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transaction_date' => ['required', 'date', 'before_or_equal:now'],
            'category_id' => ['required', Rule::enum(Category::class)->except(Category::InitialBalance)],
            'description' => ['nullable', 'string', 'max:500'],
            'tags' => ['nullable', 'array', 'max:' . TagService::MAX_TAGS],
            'tags.*' => ['string', 'max:' . TagService::MAX_RAW_LENGTH],
            'destination_account_id' => ['prohibited'],
            'fee_amount' => ['prohibited'],
        ];
    }
}

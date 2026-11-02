<?php

namespace App\Http\Requests;

use App\Enums\Category;
use App\Enums\TransactionType;
use App\Models\TransactionTemplate;
use App\Services\TagService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared store/update validation. Type-conditional fields mirror
 * SaveTransactionRequest's prohibited/required style; account links must
 * be owned by the submitting user.
 */
final class SaveTransactionTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $template = $this->route('template');

        return $template !== null
            ? $this->user()->can('update', $template)
            : $this->user()->can('create', TransactionTemplate::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_map(
            fn ($value) => $value === '' ? null : $value,
            $this->only(['account_id', 'destination_account_id', 'fee_amount', 'description']),
        ));
    }

    public function rules(): array
    {
        $isTransfer = $this->input('type') === TransactionType::Transfer->value;

        $ownedAccount = fn () => Rule::exists('accounts', 'id')->where('owner_id', $this->user()->id);

        return [
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'string', Rule::enum(TransactionType::class)],
            'category_id' => $isTransfer
                ? ['prohibited']
                : ['required', 'string', Rule::enum(Category::class)->except(Category::InitialBalance)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:500'],
            'tags' => ['nullable', 'array', 'max:' . TagService::MAX_TAGS],
            'tags.*' => ['string', 'max:' . TagService::MAX_RAW_LENGTH],
            'account_id' => ['nullable', 'integer', $ownedAccount()],
            'destination_account_id' => $isTransfer
                ? ['required', 'integer', $ownedAccount()]
                : ['prohibited'],
            'fee_amount' => $isTransfer
                ? ['nullable', 'numeric', 'min:0.01']
                : ['prohibited'],
            'decorations' => ['nullable', 'array'],
            'decorations.icon' => ['required_with:decorations', 'string', 'max:100'],
            'decorations.color' => ['required_with:decorations', 'string', 'max:100'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Enums\AccountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::enum(AccountType::class)],
            'provider_id' => ['nullable', 'integer', 'exists:providers,id'],
            'credit_card_limit' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'decorations' => ['nullable', 'array'],
            'decorations.icon' => ['required_with:decorations', 'array'],
            'decorations.icon.id' => ['required_with:decorations.icon', 'string', 'max:100'],
            'decorations.icon.value' => ['required_with:decorations.icon', 'string', 'max:100'],
            'decorations.color' => ['required_with:decorations', 'array'],
            'decorations.color.id' => ['required_with:decorations.color', 'string', 'max:100'],
            'decorations.color.value' => ['required_with:decorations.color', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }
}

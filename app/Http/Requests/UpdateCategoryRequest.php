<?php

namespace App\Http\Requests;

use App\Enums\CategoryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'cosmetics' => ['nullable', 'array'],
            'cosmetics.icon' => ['required_with:cosmetics', 'string', 'max:100'],
            'cosmetics.color' => ['required_with:cosmetics', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'type' => ['required', 'string', Rule::enum(CategoryType::class)],
            'order' => ['required', 'numeric', 'between:0,0.999'],
            'is_fixed_cost' => ['required', 'boolean'],
        ];
    }
}

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
            'decorations' => ['nullable', 'array'],
            'decorations.icon' => ['required_with:decorations', 'string', 'max:100'],
            'decorations.color' => ['required_with:decorations', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'type' => ['required', 'string', Rule::enum(CategoryType::class)],
            'order' => ['required', 'numeric', 'between:0,0.999'],
            'is_fixed_cost' => ['required', 'boolean'],
        ];
    }
}

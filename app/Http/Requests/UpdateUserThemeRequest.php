<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateUserThemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'theme' => ['required', 'string', 'max:50'],
        ];
    }
}

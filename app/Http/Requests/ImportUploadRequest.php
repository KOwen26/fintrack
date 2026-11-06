<?php

namespace App\Http\Requests;

use App\Models\Account;
use Illuminate\Foundation\Http\FormRequest;

final class ImportUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $account = $this->route('account');

        return $account instanceof Account && $this->user()->can('update', $account);
    }

    public function rules(): array
    {
        return [
            'statement' => ['required', 'file', 'mimes:xlsx', 'max:5120'],
        ];
    }
}

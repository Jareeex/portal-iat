<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Stringable;

class UpdateAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // dijaga oleh middleware role.master
    }

    /** @return array<string, array<int, string|Stringable|ValidationRule>> */
    public function rules(): array
    {
        return [
            'page_ids' => ['present', 'array'],
            'page_ids.*' => ['integer', 'distinct', 'exists:pages,id'],
            'edit_page_ids' => ['sometimes', 'array'],
            'edit_page_ids.*' => ['integer', 'distinct', 'exists:pages,id'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // dijaga oleh middleware role.master
    }

    public function rules(): array
    {
        return [
            'page_ids' => ['present', 'array'],
            'page_ids.*' => ['integer', 'distinct', 'exists:pages,id'],
        ];
    }
}

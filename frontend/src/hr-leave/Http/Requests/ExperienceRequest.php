<?php

namespace App\Modules\Hr\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExperienceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null; // otorisasi detail di controller (policy)
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['outside', 'inside'])],
            'company' => ['required', 'string', 'max:150'],
            'position' => ['nullable', 'string', 'max:150'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

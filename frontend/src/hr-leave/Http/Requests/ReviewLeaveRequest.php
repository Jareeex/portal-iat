<?php

namespace App\Modules\Hr\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null; // otorisasi detail di controller (policy review)
    }

    public function rules(): array
    {
        $isReject = $this->routeIs(config('hr.route.name').'leaves.reject');

        return [
            'review_note' => [Rule::requiredIf($isReject), 'nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return ['review_note.required' => 'Alasan penolakan wajib diisi.'];
    }
}

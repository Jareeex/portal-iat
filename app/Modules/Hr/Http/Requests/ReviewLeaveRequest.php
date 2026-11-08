<?php

namespace App\Modules\Hr\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $isReject = $this->routeIs(
            config('hr.route.name').'leave-requests.reject',
            config('hr.route.name').'leave-requests.manager-reject',
        );

        return [
            'review_note' => [Rule::requiredIf($isReject), 'nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'review_note.required' => 'Alasan penolakan wajib diisi.',
        ];
    }
}

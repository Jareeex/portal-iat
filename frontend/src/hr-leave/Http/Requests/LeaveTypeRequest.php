<?php

namespace App\Modules\Hr\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class LeaveTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('hr.manage');
    }

    public function rules(): array
    {
        $current = $this->route('leaveType')?->id;

        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'alpha_dash', 'max:30', Rule::unique('leave_types', 'code')->ignore($current)],
            'annual_quota_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'requires_attachment' => ['sometimes', 'boolean'],
            'is_paid' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** Checkbox yang tidak dicentang tidak terkirim => false */
    protected function prepareForValidation(): void
    {
        foreach (['requires_attachment', 'is_paid', 'is_active'] as $k) {
            $this->merge([$k => $this->boolean($k)]);
        }
    }
}

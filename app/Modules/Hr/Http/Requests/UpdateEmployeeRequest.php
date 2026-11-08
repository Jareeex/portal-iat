<?php

namespace App\Modules\Hr\Http\Requests;

use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Support\EmployeeRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('hr.manage');
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $employee = $this->route('employee');
        abort_unless($employee instanceof Employee, 404);

        $rules = array_merge(
            EmployeeRules::employment($employee->id, $employee->job_title),
            EmployeeRules::personal(),
        );

        if ($employee->user_id === null) {
            $rules['name'] = ['sometimes', 'required', 'string', 'max:255'];
        }

        return $rules;
    }
}

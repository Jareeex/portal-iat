<?php

namespace App\Modules\Hr\Http\Requests;

use App\Modules\Hr\Support\EmployeeRules;
use App\Modules\Hr\Support\Hr;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('hr.manage');
    }

    public function rules(): array
    {
        $employee = $this->route('employee');
        $usersTable = (new (Hr::userModel()))->getTable();

        return array_merge([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255',
                Rule::unique($usersTable, 'email')->ignore($employee->user_id)],
            'password' => ['nullable', Password::min(8)],
        ], EmployeeRules::employment($employee->id), EmployeeRules::personal());
    }
}

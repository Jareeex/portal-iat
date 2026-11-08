<?php

namespace App\Modules\Hr\Http\Requests;

use App\Modules\Hr\Support\EmployeeRules;
use App\Modules\Hr\Support\Hr;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('hr.manage');
    }

    public function rules(): array
    {
        $usersTable = (new (Hr::userModel()))->getTable();

        return array_merge([
            // akun user
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', "unique:{$usersTable},email"],
            'password' => ['required', Password::min(8)],
        ], EmployeeRules::employment(), EmployeeRules::personal());
    }
}

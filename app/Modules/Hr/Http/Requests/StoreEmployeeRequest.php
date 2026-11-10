<?php

namespace App\Modules\Hr\Http\Requests;

use App\Modules\Hr\Support\EmployeeRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('hr.manage');
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'max:2048'],
            ...EmployeeRules::employment(),
        ];
    }
}

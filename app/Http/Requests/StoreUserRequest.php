<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Stringable;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // dijaga oleh middleware role.master
    }

    /** @return array<string, array<int, string|Stringable|ValidationRule>> */
    public function rules(): array
    {
        $routeUser = $this->route('user');
        $userId = $routeUser instanceof User ? $routeUser->getKey() : $routeUser;
        $isUpdate = $userId !== null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => [$isUpdate ? 'nullable' : 'required', 'string', 'min:8'],
            'role' => ['sometimes', Rule::in([User::ROLE_MASTER, User::ROLE_USER])],
            'is_active' => ['sometimes', 'boolean'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ];
    }
}

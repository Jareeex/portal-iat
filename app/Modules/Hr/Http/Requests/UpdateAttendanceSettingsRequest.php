<?php

namespace App\Modules\Hr\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateAttendanceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && Gate::allows('hr.manage');
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'radius_meters' => ['required', 'integer', 'min:1', 'max:100000'],
        ];
    }
}

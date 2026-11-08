<?php

namespace App\Modules\Hr\Support;

use Illuminate\Validation\Rule;

class EmployeeRules
{
    /** Field yang cuma boleh diubah HR/admin */
    public static function employment(?int $ignoreId = null): array
    {
        return [
            'employee_number' => ['nullable', 'string', 'max:50', Rule::unique('employees', 'employee_number')->ignore($ignoreId)],
            'job_title' => ['nullable', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:120'],
            'office' => ['nullable', 'string', 'max:120'],
            'employment_status' => ['nullable', Rule::in(config('hr.options.employment_statuses'))],
            'join_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:join_date'],
        ];
    }

    /** Field data diri: HR boleh, karyawan boleh edit punya sendiri */
    public static function personal(): array
    {
        $o = config('hr.options');

        return [
            'place_of_birth' => ['nullable', 'string', 'max:100'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in($o['genders'])],
            'marital_status' => ['nullable', Rule::in($o['marital_statuses'])],
            'religion' => ['nullable', Rule::in($o['religions'])],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:30'],
            'photo' => ['nullable', 'image', 'max:2048'],

            'ktp_number' => ['nullable', 'digits:16'],
            'ktp_issued_at' => ['nullable', 'date'],
            'ktp_expired_at' => ['nullable', 'date', 'after_or_equal:ktp_issued_at'],
            'ktp_place' => ['nullable', 'string', 'max:100'],
            'passport_number' => ['nullable', 'string', 'max:20'],
            'passport_issued_at' => ['nullable', 'date'],
            'passport_expired_at' => ['nullable', 'date', 'after_or_equal:passport_issued_at'],
            'passport_place' => ['nullable', 'string', 'max:100'],
            'npwp_number' => ['nullable', 'regex:/^[0-9.\-]{15,20}$/'],

            'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'height_cm' => ['nullable', 'numeric', 'min:0', 'max:300'],
            'blood_type' => ['nullable', Rule::in($o['blood_types'])],

            'highest_education' => ['nullable', Rule::in($o['educations'])],
            'education_institution' => ['nullable', 'string', 'max:150'],
            'education_major' => ['nullable', 'string', 'max:150'],
            'graduated_year' => ['nullable', 'integer', 'between:1950,'.(date('Y') + 1)],
        ];
    }
}

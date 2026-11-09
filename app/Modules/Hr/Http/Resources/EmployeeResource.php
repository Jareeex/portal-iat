<?php

namespace App\Modules\Hr\Http\Resources;

use App\Modules\Hr\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/** @mixin Employee */
class EmployeeResource extends JsonResource
{
    private const DATES = [
        'date_of_birth', 'join_date', 'end_date', 'ktp_issued_at', 'ktp_expired_at',
        'passport_issued_at', 'passport_expired_at',
    ];

    private const PLAIN = [
        'id', 'user_id', 'name', 'employee_number', 'job_title', 'office', 'employment_status',
        'place_of_birth', 'gender', 'marital_status', 'religion', 'address', 'phone',
        'ktp_place', 'passport_place', 'weight_kg', 'height_cm', 'blood_type',
        'highest_education', 'education_institution', 'education_major', 'graduated_year',
    ];

    private const LIST_FIELDS = [
        'id', 'user_id', 'name', 'employee_number', 'job_title', 'office', 'employment_status', 'join_date', 'end_date',
    ];

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Employee $employee */
        $employee = $this->resource;
        $list = $request->routeIs(config('hr.route.name').'employees.index');
        $data = $employee->only($list ? self::LIST_FIELDS : self::PLAIN);

        foreach (self::DATES as $date) {
            if (array_key_exists($date, $data)) {
                $data[$date] = $employee->{$date}?->toDateString();
            }
        }

        return [
            ...$data,
            'name' => $employee->user_id === null ? $employee->name : $employee->user->name,
            ...($list ? [] : [
                'age' => $employee->age,
                'photo_url' => $employee->photo_url,
                'ktp_number' => $employee->ktp_number ? Str::mask($employee->ktp_number, '*', 0, -4) : null,
                'passport_number' => $employee->passport_number ? Str::mask($employee->passport_number, '*', 0, -4) : null,
                'npwp_number' => $employee->npwp_number ? Str::mask($employee->npwp_number, '*', 0, -4) : null,
            ]),
            'user' => $this->whenLoaded('user', fn () => $employee->user ? [
                'id' => $employee->user->getKey(),
                'name' => $employee->user->name,
                'email' => $employee->user->email,
                'role' => $employee->user->role,
                'is_active' => $employee->user->is_active,
                'department' => $employee->user->department?->only(['id', 'name', 'code']),
            ] : null),
            'experiences' => $this->whenLoaded('experiences'),
            'created_at' => $employee->created_at,
            'updated_at' => $employee->updated_at,
        ];
    }
}

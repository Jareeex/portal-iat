<?php

namespace App\Modules\Hr\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class EmployeeResource extends JsonResource
{
    private const DATES = [
        'date_of_birth', 'join_date', 'end_date', 'ktp_issued_at', 'ktp_expired_at',
        'passport_issued_at', 'passport_expired_at',
    ];

    private const PLAIN = [
        'id', 'user_id', 'employee_number', 'job_title', 'department', 'office', 'employment_status',
        'place_of_birth', 'gender', 'marital_status', 'religion', 'address', 'phone',
        'ktp_place', 'passport_place', 'weight_kg', 'height_cm', 'blood_type',
        'highest_education', 'education_institution', 'education_major', 'graduated_year',
    ];

    public function toArray($request): array
    {
        $list = $request->routeIs(config('hr.route.name').'employees.index');
        $mask = fn ($v) => $v && $list ? Str::mask($v, '*', 0, -4) : $v;

        $data = $this->resource->only(self::PLAIN);

        foreach (self::DATES as $d) {
            $data[$d] = $this->{$d}?->toDateString();
        }

        return $data + [
            'age' => $this->age,
            'photo_url' => $this->photo_url,
            'ktp_number' => $mask($this->ktp_number),
            'passport_number' => $mask($this->passport_number),
            'npwp_number' => $mask($this->npwp_number),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->getKey(), 'name' => $this->user->name, 'email' => $this->user->email,
            ]),
            'experiences' => $this->whenLoaded('experiences'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

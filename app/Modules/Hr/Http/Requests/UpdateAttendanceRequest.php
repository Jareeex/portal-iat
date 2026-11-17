<?php

namespace App\Modules\Hr\Http\Requests;

use App\Models\User;
use App\Modules\Hr\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class UpdateAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && Gate::allows('hr.manage');
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'clock_in_time' => ['required', 'date_format:H:i'],
            'clock_out_time' => ['nullable', 'date_format:H:i'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->any()) {
                return;
            }

            $attendance = $this->route('attendance');
            $clockOutTime = $this->input('clock_out_time');

            if (! $attendance instanceof Attendance) {
                return;
            }

            if ($attendance->clock_out_at !== null && (! is_string($clockOutTime) || $clockOutTime === '')) {
                $validator->errors()->add('clock_out_time', 'Jam clock-out yang sudah tercatat tidak dapat dihapus.');

                return;
            }

            if (! is_string($clockOutTime) || $clockOutTime === '') {
                return;
            }

            $date = $attendance->attendance_date->toDateString();
            $timezone = (string) config('hr.attendance.timezone');
            $clockIn = Carbon::parse("{$date} ".$this->input('clock_in_time'), $timezone);
            $clockOut = Carbon::parse("{$date} {$clockOutTime}", $timezone);

            if ($clockOut->lessThanOrEqualTo($clockIn)) {
                $validator->errors()->add('clock_out_time', 'Jam pulang harus setelah jam masuk.');
            }
        });
    }
}

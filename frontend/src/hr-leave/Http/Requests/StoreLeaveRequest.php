<?php

namespace App\Modules\Hr\Http\Requests;

use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\LeaveType;
use App\Modules\Hr\Support\LeaveService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $min = now()->subDays((int) config('hr.leave.backdate_days', 7))->toDateString();

        return [
            'leave_type_id' => ['required', Rule::exists('leave_types', 'id')->where('is_active', true)],
            'start_date' => ['required', 'date', "after_or_equal:{$min}"],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:1000'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($v->errors()->hasAny(['leave_type_id', 'start_date', 'end_date'])) {
                return;
            }

            $employee = Employee::forUser($this->user());
            $type = LeaveType::find($this->input('leave_type_id'));
            $start = Carbon::parse($this->input('start_date'));
            $end = Carbon::parse($this->input('end_date'));
            $days = LeaveService::countDays($start, $end);

            if ($days < 1) {
                $v->errors()->add('start_date', 'Rentang tanggal tidak mengandung hari kerja.');
            }

            if (LeaveService::overlaps($employee, $start, $end)) {
                $v->errors()->add('start_date', 'Bertabrakan dengan pengajuan lain yang masih pending/disetujui.');
            }

            $remaining = LeaveService::remaining($employee, $type, $start->year);
            if ($remaining !== null && $days > $remaining) {
                $v->errors()->add('end_date', "Sisa jatah {$type->name} tahun {$start->year} tinggal {$remaining} hari (diajukan {$days} hari).");
            }

            if ($type->requires_attachment && ! $this->hasFile('attachment')) {
                $v->errors()->add('attachment', "Lampiran wajib untuk {$type->name}.");
            }
        });
    }
}

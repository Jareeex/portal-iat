<?php

namespace App\Modules\Hr\Http\Requests;

use App\Models\User;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Hr\Models\LeaveType;
use App\Modules\Hr\Support\LeaveService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && Gate::allows('hr.manage');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $minimumDate = now()->subDays((int) config('hr.leave.backdate_days', 7))->toDateString();

        return [
            'leave_type_id' => ['required', Rule::exists('leave_types', 'id')->where('is_active', true)],
            'start_date' => ['required', 'date', "after_or_equal:{$minimumDate}"],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:1000'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->any()) {
                return;
            }

            $leave = $this->route('leaveRequest');
            $user = $this->user();
            if (! $leave instanceof LeaveRequest || ! $user instanceof User) {
                return;
            }

            $employee = $leave->employee;
            $leaveType = LeaveType::findOrFail($this->integer('leave_type_id'));
            $start = Carbon::parse($this->input('start_date'));
            $end = Carbon::parse($this->input('end_date'));
            $days = LeaveService::countDays($start, $end);

            if ($days < 1) {
                $validator->errors()->add('start_date', 'Rentang tanggal tidak mengandung hari kerja.');
            }

            if ($leaveType->requires_attachment && $leave->attachment_path === null) {
                $validator->errors()->add(
                    'leave_type_id',
                    "Lampiran wajib untuk {$leaveType->name}; HR tidak dapat mengganti jenis cuti tanpa lampiran.",
                );
            }

            if (LeaveService::overlaps($employee, $start, $end, $leave->id)) {
                $validator->errors()->add('start_date', 'Bertabrakan dengan pengajuan lain yang masih menunggu atau disetujui.');
            }

            $remainingDays = LeaveService::remaining($employee, $leaveType, $start->year, $leave->id);
            if ($remainingDays !== null && $days > $remainingDays) {
                $validator->errors()->add(
                    'end_date',
                    "Sisa jatah {$leaveType->name} tahun {$start->year} tinggal {$remainingDays} hari.",
                );
            }
        });
    }
}

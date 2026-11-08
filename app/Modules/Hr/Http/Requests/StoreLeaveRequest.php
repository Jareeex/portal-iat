<?php

namespace App\Modules\Hr\Http\Requests;

use App\Models\User;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\JobPosition;
use App\Modules\Hr\Models\LeaveType;
use App\Modules\Hr\Support\LeaveService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $minimumDate = now()->subDays((int) config('hr.leave.backdate_days', 7))->toDateString();

        return [
            'leave_type_id' => ['required', Rule::exists('leave_types', 'id')->where('is_active', true)],
            'manager_id' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'start_date' => ['required', 'date', "after_or_equal:{$minimumDate}"],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:1000'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->any()) {
                return;
            }

            $user = $this->user();
            if (! $user instanceof User) {
                return;
            }

            $employee = Employee::forUser($user);
            $managerIds = JobPosition::active()->where('is_leave_manager', true)->pluck('name');
            $managerIsEligible = User::query()
                ->whereKey($this->integer('manager_id'))
                ->where('is_active', true)
                ->where('department_id', $user->department_id)
                ->whereNotNull('department_id')
                ->where('id', '!=', $user->getKey())
                ->whereHas('employee', fn ($query) => $query->whereIn('job_title', $managerIds))
                ->exists();

            if (! $managerIsEligible) {
                $validator->errors()->add('manager_id', 'Pilih manager aktif dari departemen yang sama.');
            }

            $leaveType = LeaveType::findOrFail($this->integer('leave_type_id'));
            $start = Carbon::parse($this->input('start_date'));
            $end = Carbon::parse($this->input('end_date'));
            $days = LeaveService::countDays($start, $end);

            if ($days < 1) {
                $validator->errors()->add('start_date', 'Rentang tanggal tidak mengandung hari kerja.');
            }

            if (LeaveService::overlaps($employee, $start, $end)) {
                $validator->errors()->add('start_date', 'Bertabrakan dengan pengajuan lain yang masih menunggu atau disetujui.');
            }

            $remainingDays = LeaveService::remaining($employee, $leaveType, $start->year);
            if ($remainingDays !== null && $days > $remainingDays) {
                $validator->errors()->add(
                    'end_date',
                    "Sisa jatah {$leaveType->name} tahun {$start->year} tinggal {$remainingDays} hari.",
                );
            }

            if ($leaveType->requires_attachment && ! $this->hasFile('attachment')) {
                $validator->errors()->add('attachment', "Lampiran wajib untuk {$leaveType->name}.");
            }
        });
    }
}

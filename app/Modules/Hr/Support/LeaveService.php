<?php

namespace App\Modules\Hr\Support;

use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Hr\Models\LeaveType;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;

class LeaveService
{
    public static function countDays(CarbonInterface $start, CarbonInterface $end): int
    {
        $excludeWeekends = config('hr.leave.exclude_weekends', true);
        $holidays = config('hr.leave.holidays', []);
        $days = 0;

        foreach (CarbonPeriod::create($start->copy()->startOfDay(), $end->copy()->startOfDay()) as $date) {
            if ($excludeWeekends && $date->isWeekend()) {
                continue;
            }

            if (in_array($date->toDateString(), $holidays, true)) {
                continue;
            }

            $days++;
        }

        return $days;
    }

    public static function used(
        Employee $employee,
        LeaveType $type,
        int $year,
        ?int $excludedRequestId = null,
    ): int {
        return (int) LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $type->id)
            ->whereIn('status', [
                LeaveRequest::MANAGER_PENDING,
                LeaveRequest::HR_PENDING,
                LeaveRequest::APPROVED,
            ])
            ->whereYear('start_date', $year)
            ->when($excludedRequestId, fn ($query) => $query->where('id', '!=', $excludedRequestId))
            ->sum('days');
    }

    public static function remaining(
        Employee $employee,
        LeaveType $type,
        int $year,
        ?int $excludedRequestId = null,
    ): ?int {
        if ($type->annual_quota_days === null) {
            return null;
        }

        return max(0, $type->annual_quota_days - self::used($employee, $type, $year, $excludedRequestId));
    }

    public static function overlaps(
        Employee $employee,
        CarbonInterface $start,
        CarbonInterface $end,
        ?int $excludedRequestId = null,
    ): bool {
        return LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', [
                LeaveRequest::MANAGER_PENDING,
                LeaveRequest::HR_PENDING,
                LeaveRequest::APPROVED,
            ])
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->when($excludedRequestId, fn ($query) => $query->where('id', '!=', $excludedRequestId))
            ->exists();
    }
}

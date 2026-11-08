<?php

namespace App\Modules\Hr\Policies;

use App\Models\User;
use App\Modules\Hr\Models\LeaveRequest;
use Illuminate\Support\Facades\Gate;

class LeaveRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return Gate::forUser($user)->allows('hr.manage');
    }

    public function view(User $user, LeaveRequest $leave): bool
    {
        return Gate::forUser($user)->allows('hr.manage')
            || (
                $leave->manager_id === $user->getKey()
                && $leave->status === LeaveRequest::MANAGER_PENDING
            );
    }

    public function review(User $user, LeaveRequest $leave): bool
    {
        return Gate::forUser($user)->allows('hr.manage')
            && $leave->status === LeaveRequest::HR_PENDING;
    }

    public function managerReview(User $user, LeaveRequest $leave): bool
    {
        return $leave->manager_id === $user->getKey()
            && $leave->status === LeaveRequest::MANAGER_PENDING;
    }
}

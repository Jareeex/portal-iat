<?php

namespace App\Modules\Hr\Policies;

use App\Modules\Hr\Models\LeaveRequest;
use Illuminate\Support\Facades\Gate;

class LeaveRequestPolicy
{
    private function manage($user): bool
    {
        return Gate::forUser($user)->allows('hr.manage');
    }

    private function own($user, LeaveRequest $leave): bool
    {
        return $leave->employee?->user_id === $user->getKey();
    }

    /** Inbox HR */
    public function viewAny($user): bool
    {
        return $this->manage($user);
    }

    public function view($user, LeaveRequest $leave): bool
    {
        return $this->manage($user) || $this->own($user, $leave);
    }

    /** Pemilik batalkan sendiri selama masih pending */
    public function cancel($user, LeaveRequest $leave): bool
    {
        return $this->own($user, $leave) && $leave->isPending();
    }

    /** HR approve/reject; tidak boleh review pengajuan sendiri */
    public function review($user, LeaveRequest $leave): bool
    {
        return $this->manage($user) && ! $this->own($user, $leave) && $leave->isPending();
    }
}

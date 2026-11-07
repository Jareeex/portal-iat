<?php

namespace App\Modules\Hr\Policies;

use App\Models\User;
use App\Modules\Hr\Models\Employee;
use App\Services\AccessService;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isMaster() || app(AccessService::class)->canAccess($user, 'hr-employees');
    }

    public function view(User $user, Employee $employee): bool
    {
        return $user->isMaster()
            || $employee->user_id === $user->getKey()
            || app(AccessService::class)->canAccess($user, 'hr-employees');
    }

    public function updateProfile(User $user, Employee $employee): bool
    {
        return $user->isMaster()
            || $employee->user_id === $user->getKey()
            || app(AccessService::class)->canEdit($user, 'hr-employees');
    }
}

<?php

namespace App\Modules\Hr\Policies;

use App\Modules\Hr\Models\Employee;
use Illuminate\Support\Facades\Gate;

class EmployeePolicy
{
    private function manage($user): bool
    {
        return Gate::forUser($user)->allows('hr.manage');
    }

    private function own($user, Employee $employee): bool
    {
        return $employee->user_id === $user->getKey();
    }

    public function viewAny($user): bool
    {
        return $this->manage($user);
    }

    public function view($user, Employee $employee): bool
    {
        return $this->manage($user) || $this->own($user, $employee);
    }

    public function create($user): bool
    {
        return $this->manage($user);
    }

    /** Edit penuh (data kepegawaian) - HR saja */
    public function update($user, Employee $employee): bool
    {
        return $this->manage($user);
    }

    /** Edit data diri + pengalaman kerja - HR atau pemilik profil */
    public function updateProfile($user, Employee $employee): bool
    {
        return $this->manage($user) || $this->own($user, $employee);
    }

    public function delete($user, Employee $employee): bool
    {
        return $this->manage($user) && ! $this->own($user, $employee);
    }
}

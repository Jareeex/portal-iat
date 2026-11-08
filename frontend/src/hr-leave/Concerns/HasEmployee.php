<?php

namespace App\Modules\Hr\Concerns;

use App\Modules\Hr\Models\Employee;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Tempel di model User:  use \App\Modules\Hr\Concerns\HasEmployee;
 */
trait HasEmployee
{
    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    /** Selalu return profil employee (dibuat kalau belum ada). */
    public function employeeProfile(): Employee
    {
        return $this->employee ?? Employee::forUser($this);
    }

    /**
     * Default false. Override di model User lu, contoh:
     *   public function isHrAdmin(): bool { return $this->hasRole('hr'); }
     */
    public function isHrAdmin(): bool
    {
        return false;
    }
}

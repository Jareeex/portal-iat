<?php

namespace App\Modules\Hr\Concerns;

use App\Models\User;
use App\Modules\Hr\Models\Employee;
use Illuminate\Database\Eloquent\Relations\HasOne;

trait HasEmployee
{
    protected static function bootHasEmployee(): void
    {
        static::created(static function (User $user): void {
            $user->employee()->create();
        });
    }

    /** @return HasOne<Employee, $this> */
    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function employeeProfile(): Employee
    {
        return $this->employee()->firstOrCreate();
    }
}

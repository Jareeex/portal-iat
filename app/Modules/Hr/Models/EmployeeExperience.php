<?php

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property int $employee_id */
class EmployeeExperience extends Model
{
    protected $table = 'employee_experiences';

    protected $fillable = ['type', 'company', 'position', 'start_date', 'end_date', 'description'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

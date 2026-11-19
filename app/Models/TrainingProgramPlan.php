<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingProgramPlan extends Model
{
    protected $fillable = ['program_id', 'crew_id', 'year', 'month', 'due_date', 'overdue', 'not_executed', 'remarks'];

    protected $casts = ['due_date' => 'date:Y-m-d', 'overdue' => 'boolean', 'not_executed' => 'boolean'];

    public function program(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class, 'program_id');
    }

    public function crew(): BelongsTo
    {
        return $this->belongsTo(Crew::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TrainingProgramActual extends Model
{
    protected $fillable = ['program_id', 'year', 'month', 'held_on', 'end_on', 'venue', 'notes', 'user_id'];

    protected $casts = ['held_on' => 'date:Y-m-d', 'end_on' => 'date:Y-m-d'];

    public function program(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class, 'program_id');
    }

    public function crews(): BelongsToMany
    {
        return $this->belongsToMany(Crew::class, 'program_actual_crew', 'actual_id', 'crew_id');
    }
}

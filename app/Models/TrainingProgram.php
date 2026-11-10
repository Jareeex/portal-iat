<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingProgram extends Model
{
    public const CATEGORIES = [
        'rw' => 'Rotary Wing', 'fw' => 'Fixed Wing', 'fa' => 'FA', 'foo' => 'FOO & HLO',
        'mandatory' => 'Mandatory Training', 'additional' => 'Additional Training',
    ];

    protected $fillable = ['category', 'name', 'sort_order'];

    public function types(): BelongsToMany
    {
        return $this->belongsToMany(TrainingType::class, 'training_program_types', 'program_id', 'type_id');
    }

    public function plans(): HasMany
    {
        return $this->hasMany(TrainingProgramPlan::class, 'program_id');
    }

    public function actuals(): HasMany
    {
        return $this->hasMany(TrainingProgramActual::class, 'program_id');
    }
}

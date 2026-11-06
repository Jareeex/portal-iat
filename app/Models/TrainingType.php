<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingType extends Model
{
    protected $fillable = ['group', 'section', 'name', 'validity', 'sort_order'];

    public function crewTrainings(): HasMany
    {
        return $this->hasMany(CrewTraining::class);
    }
}

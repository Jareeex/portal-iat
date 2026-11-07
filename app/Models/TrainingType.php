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

    public static function pilotRoleFromName(string $name): ?string
    {
        return preg_match('/^Pilot Proficiency Check\s*[–—-]\s*(?:Simulator|Aircraft)\s+(PIC|SIC)$/u', $name, $matches)
            ? $matches[1]
            : null;
    }
}

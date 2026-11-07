<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

class TrainingAircraft extends Model
{
    protected $table = 'training_aircrafts';

    protected $fillable = ['group_key', 'name', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsToMany<Crew, $this, Pivot, 'pivot'> */
    public function crews(): BelongsToMany
    {
        return $this->belongsToMany(Crew::class, 'crew_training_aircraft')->withTimestamps();
    }
}

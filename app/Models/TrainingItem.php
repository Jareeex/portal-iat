<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingItem extends Model
{
    protected $fillable = ['crew_id', 'name', 'next_check'];

    protected function casts(): array
    {
        return ['next_check' => 'date'];
    }

    public function crew(): BelongsTo
    {
        return $this->belongsTo(Crew::class);
    }
}

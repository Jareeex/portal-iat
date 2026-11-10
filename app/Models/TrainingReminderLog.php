<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingReminderLog extends Model
{
    protected $fillable = [
        'crew_training_id',
        'recipient_user_id',
        'due_date',
        'stage',
    ];

    /** @return BelongsTo<CrewTraining, $this> */
    public function crewTraining(): BelongsTo
    {
        return $this->belongsTo(CrewTraining::class);
    }
}

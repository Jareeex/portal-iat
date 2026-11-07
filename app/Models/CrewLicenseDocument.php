<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrewLicenseDocument extends Model
{
    protected $fillable = ['crew_id', 'uploaded_by', 'original_name', 'path'];

    protected $hidden = ['path'];

    public function crew(): BelongsTo
    {
        return $this->belongsTo(Crew::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

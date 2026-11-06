<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Crew extends Model
{
    protected $fillable = ['name', 'code', 'category', 'fleet', 'position', 'sort_order'];

    public function items(): HasMany
    {
        return $this->hasMany(TrainingItem::class);
    }
}

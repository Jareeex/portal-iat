<?php

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property int $sort_order
 * @property bool $is_active
 * @property bool $is_leave_manager
 */
class JobPosition extends Model
{
    protected $fillable = ['name', 'sort_order', 'is_active', 'is_leave_manager'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'is_leave_manager' => 'boolean',
        ];
    }

    /**
     * @param  Builder<JobPosition>  $query
     * @return Builder<JobPosition>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}

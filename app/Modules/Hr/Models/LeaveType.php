<?php

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property int|null $annual_quota_days
 * @property bool $requires_attachment
 * @property bool $is_paid
 * @property bool $is_active
 */
class LeaveType extends Model
{
    protected $table = 'leave_types';

    protected $fillable = [
        'name',
        'code',
        'annual_quota_days',
        'requires_attachment',
        'is_paid',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'annual_quota_days' => 'integer',
            'requires_attachment' => 'boolean',
            'is_paid' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<LeaveType>  $query
     * @return Builder<LeaveType>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}

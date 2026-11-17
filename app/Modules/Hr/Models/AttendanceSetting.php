<?php

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property float $latitude
 * @property float $longitude
 * @property int $radius_meters
 */
class AttendanceSetting extends Model
{
    protected $fillable = ['latitude', 'longitude', 'radius_meters'];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_meters' => 'integer',
        ];
    }
}

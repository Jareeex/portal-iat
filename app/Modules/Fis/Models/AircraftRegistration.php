<?php

namespace App\Modules\Fis\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $registration
 * @property string $aircraft_type
 * @property int $seat_capacity
 */
class AircraftRegistration extends Model
{
    protected $fillable = ['registration', 'aircraft_type', 'seat_capacity'];

    /** @return HasMany<Flight, $this> */
    public function flights(): HasMany
    {
        return $this->hasMany(Flight::class);
    }
}

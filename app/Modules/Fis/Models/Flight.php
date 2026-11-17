<?php

namespace App\Modules\Fis\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $flight_no
 * @property Carbon $date
 * @property string $chartered_by
 * @property int $aircraft_registration_id
 * @property array<int, array<string, mixed>> $schedules
 * @property array<int, array<string, mixed>> $crews
 * @property array<int, array<string, mixed>> $handling_agents
 * @property array<int, array<string, mixed>> $passengers
 * @property array<int, array<string, mixed>> $accommodations
 * @property array<string, string|null> $other
 * @property-read AircraftRegistration|null $aircraftRegistration
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Flight extends Model
{
    protected $fillable = [
        'flight_no',
        'date',
        'chartered_by',
        'aircraft_registration_id',
        'schedules',
        'crews',
        'handling_agents',
        'passengers',
        'accommodations',
        'other',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'schedules' => 'array',
            'crews' => 'array',
            'handling_agents' => 'array',
            'passengers' => 'array',
            'accommodations' => 'array',
            'other' => 'array',
        ];
    }

    /** @return BelongsTo<AircraftRegistration, $this> */
    public function aircraftRegistration(): BelongsTo
    {
        return $this->belongsTo(AircraftRegistration::class);
    }

    public function getTotalDurationMinutesAttribute(): int
    {
        return (int) collect($this->schedules)->sum('duration_minutes');
    }
}

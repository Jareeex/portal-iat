<?php

namespace App\Modules\Fis\Http\Resources;

use App\Modules\Fis\Models\Flight;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Flight */
class FlightResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'flight_no' => $this->flight_no,
            'date' => $this->date->format('Y-m-d'),
            'chartered_by' => $this->chartered_by,
            'aircraft_registration' => $this->whenLoaded('aircraftRegistration', fn () => [
                'id' => $this->aircraftRegistration->id,
                'registration' => $this->aircraftRegistration->registration,
                'aircraft_type' => $this->aircraftRegistration->aircraft_type,
                'seat_capacity' => $this->aircraftRegistration->seat_capacity,
            ]),
            'schedules' => $this->schedules,
            'crews' => $this->crews ?? [],
            'handling_agents' => $this->handling_agents ?? [],
            'passengers' => $this->passengers ?? [],
            'accommodations' => $this->accommodations ?? [],
            'other' => $this->other ?? [],
            'total_duration_minutes' => $this->total_duration_minutes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

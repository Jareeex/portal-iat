<?php

namespace App\Modules\Fis\Http\Requests;

use App\Modules\Fis\Models\AircraftRegistration;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class FlightRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('fis.manage');
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'flight_no' => ['required', 'string', 'max:20'],
            'date' => ['required', 'date'],
            'chartered_by' => ['required', 'string', 'max:150'],
            'aircraft_registration_id' => ['required', 'integer', 'exists:aircraft_registrations,id'],
            'schedules' => ['required', 'array', 'min:1', 'max:30'],
            'schedules.*.date' => ['required', 'date'],
            'schedules.*.from' => ['required', 'string', 'max:10'],
            'schedules.*.to' => ['required', 'string', 'max:10'],
            'schedules.*.etd' => ['required', 'date'],
            'schedules.*.eta' => ['required', 'date'],
            'schedules.*.remarks' => ['nullable', 'string', 'max:500'],
            'crews' => ['nullable', 'array', 'max:50'],
            'crews.*.name' => ['required', 'string', 'max:100'],
            'crews.*.position' => ['nullable', 'string', 'max:100'],
            'crews.*.phone' => ['nullable', 'string', 'max:30'],
            'crews.*.remarks' => ['nullable', 'string', 'max:500'],
            'handling_agents' => ['nullable', 'array', 'max:30'],
            'handling_agents.*.location' => ['required', 'string', 'max:10'],
            'handling_agents.*.company' => ['nullable', 'string', 'max:150'],
            'handling_agents.*.contact_person' => ['nullable', 'string', 'max:100'],
            'handling_agents.*.phone' => ['nullable', 'string', 'max:30'],
            'passengers' => ['nullable', 'array', 'max:500'],
            'passengers.*.name' => ['required', 'string', 'max:100'],
            'passengers.*.remarks' => ['nullable', 'string', 'max:500'],
            'accommodations' => ['nullable', 'array', 'max:30'],
            'accommodations.*.location' => ['required', 'string', 'max:10'],
            'accommodations.*.hotel' => ['required', 'string', 'max:150'],
            'accommodations.*.address' => ['nullable', 'string', 'max:500'],
            'accommodations.*.phone' => ['nullable', 'string', 'max:30'],
            'other' => ['nullable', 'array'],
            'other.flight_sec_clearance' => ['nullable', 'string', 'max:500'],
            'other.flight_approval' => ['nullable', 'string', 'max:500'],
            'other.landing_permit' => ['nullable', 'string', 'max:500'],
            'other.overflight_permit' => ['nullable', 'string', 'max:500'],
            'other.fuel_on_board' => ['nullable', 'string', 'max:500'],
            'other.notam' => ['nullable', 'string', 'max:500'],
            'other.parking_stand' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                foreach ($this->input('schedules', []) as $index => $leg) {
                    if (Carbon::parse($leg['eta'])->lte(Carbon::parse($leg['etd']))) {
                        $validator->errors()->add(
                            "schedules.$index.eta",
                            'ETA harus lebih besar dari ETD (UTC).',
                        );
                    }
                }

                $aircraft = AircraftRegistration::find($this->integer('aircraft_registration_id'));
                if ($aircraft && count($this->input('passengers') ?? []) > $aircraft->seat_capacity) {
                    $validator->errors()->add(
                        'passengers',
                        "Jumlah penumpang melebihi kapasitas kursi pesawat ({$aircraft->seat_capacity}).",
                    );
                }
            },
        ];
    }
}

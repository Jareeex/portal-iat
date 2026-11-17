<?php

namespace App\Modules\Fis\Http\Controllers;

use App\Modules\Fis\Models\Airport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AirportController
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);
        $term = trim($filters['q']);
        $code = strtoupper($term);

        $airports = Airport::query()
            ->where(function ($query) use ($term, $code): void {
                $query->where('iata_code', $code)
                    ->orWhere('icao_code', $code)
                    ->orWhere('ident', $code)
                    ->orWhere('name', 'like', "%{$term}%")
                    ->orWhere('keywords', 'like', "%{$term}%")
                    ->orWhere('municipality', 'like', "%{$term}%")
                    ->orWhere('country_name', 'like', "%{$term}%")
                    ->orWhere('iso_country', 'like', "%{$code}%")
                    ->orWhere('iata_code', 'like', "%{$code}%")
                    ->orWhere('icao_code', 'like', "%{$code}%")
                    ->orWhere('ident', 'like', "%{$code}%");
            })
            ->orderByRaw(
                'CASE WHEN iata_code = ? OR icao_code = ? OR ident = ? THEN 0 ELSE 1 END',
                [$code, $code, $code],
            )
            ->orderByDesc('scheduled_service')
            ->orderByRaw("CASE type WHEN 'large_airport' THEN 0 WHEN 'medium_airport' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->limit(40)
            ->get();

        return response()->json($airports->map(fn (Airport $airport): array => [
            'code' => $airport->code,
            'ident' => $airport->ident,
            'iata_code' => $airport->iata_code,
            'icao_code' => $airport->icao_code,
            'name' => $airport->name,
            'keywords' => $airport->keywords,
            'municipality' => $airport->municipality,
            'country_name' => $airport->country_name,
            'iso_country' => $airport->iso_country,
            'type' => $airport->type,
            'scheduled_service' => $airport->scheduled_service,
        ]));
    }
}

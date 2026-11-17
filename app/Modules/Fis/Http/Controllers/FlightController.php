<?php

namespace App\Modules\Fis\Http\Controllers;

use App\Modules\Fis\Http\Requests\FlightRequest;
use App\Modules\Fis\Http\Resources\FlightResource;
use App\Modules\Fis\Models\AircraftRegistration;
use App\Modules\Fis\Models\Airport;
use App\Modules\Fis\Models\Flight;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class FlightController
{
    public function aircraft(): JsonResponse
    {
        return response()->json(
            AircraftRegistration::query()
                ->orderBy('registration')
                ->get(['id', 'registration', 'aircraft_type', 'seat_capacity']),
        );
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $flights = Flight::query()
            ->with('aircraftRegistration')
            ->when($filters['q'] ?? null, function ($query, string $term): void {
                $term = trim($term);
                $query->where(function ($nested) use ($term): void {
                    $nested->where('flight_no', 'like', "%{$term}%")
                        ->orWhere('chartered_by', 'like', "%{$term}%")
                        ->orWhereHas('aircraftRegistration', fn ($aircraft) => $aircraft
                            ->where('registration', 'like', "%{$term}%"));
                });
            })
            ->when($filters['date_from'] ?? null, fn ($query, string $date) => $query->whereDate('date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, string $date) => $query->whereDate('date', '<=', $date))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(config('fis.per_page'))
            ->withQueryString();

        return FlightResource::collection($flights);
    }

    public function report(Flight $flight): Response
    {
        $flight->load('aircraftRegistration');
        $codes = collect($flight->schedules)
            ->flatMap(fn (array $leg): array => [$leg['from'] ?? '', $leg['to'] ?? ''])
            ->merge(collect($flight->handling_agents ?? [])->pluck('location'))
            ->merge(collect($flight->accommodations ?? [])->pluck('location'))
            ->filter()
            ->map(fn (string $code): string => strtoupper($code))
            ->unique()
            ->values();

        $airports = [];
        if ($codes->isNotEmpty()) {
            Airport::query()
                ->whereIn('iata_code', $codes)
                ->orWhereIn('icao_code', $codes)
                ->orWhereIn('ident', $codes)
                ->get()
                ->each(function (Airport $airport) use (&$airports): void {
                    foreach ([$airport->iata_code, $airport->icao_code, $airport->ident] as $code) {
                        if ($code) {
                            $airports[strtoupper($code)] = $airport;
                        }
                    }
                });
        }

        $helicopterImage = match (strtoupper($flight->aircraftRegistration->registration)) {
            'PK-TPE' => 'tpe.svg',
            'PK-TFS' => 'tfs.svg',
            'PK-TPG' => 'tpg.svg',
            default => null,
        };

        $pdf = Pdf::loadView('fis::pdf.flight-report', [
            'flight' => $flight,
            'airports' => $airports,
            'generatedAt' => now(),
            'helicopterImage' => $helicopterImage ? $this->svgDataUri($helicopterImage) : null,
            'iatLogo' => $this->svgDataUri('logo-iat.svg'),
        ])->setPaper('a4', 'portrait');

        $pdf->getDomPDF()->getCanvas()->page_text(
            405,
            820,
            'V.002 | {DATE d/m/Y H:i} | {PAGE_NUM} of {PAGE_COUNT}',
            'DejaVu Sans',
            6,
            [0.2, 0.2, 0.2],
        );

        return $pdf->download('flight-information-'.$flight->flight_no.'-'.now()->format('Ymd').'.pdf');
    }

    public function store(FlightRequest $request): JsonResponse
    {
        $flight = DB::transaction(fn () => Flight::create($this->flightData($request->validated())));

        return (new FlightResource($flight->load('aircraftRegistration')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(FlightRequest $request, Flight $flight): FlightResource
    {
        DB::transaction(fn () => $flight->update($this->flightData($request->validated())));

        return new FlightResource($flight->fresh('aircraftRegistration'));
    }

    public function destroy(Flight $flight): JsonResponse
    {
        $flight->delete();

        return response()->json([], 204);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function flightData(array $data): array
    {
        $data['schedules'] = array_map(static function (array $leg): array {
            $etd = Carbon::parse($leg['etd']);
            $eta = Carbon::parse($leg['eta']);

            return [
                ...$leg,
                'from' => strtoupper($leg['from']),
                'to' => strtoupper($leg['to']),
                'duration_minutes' => (int) $etd->diffInMinutes($eta),
            ];
        }, $data['schedules']);

        foreach (['crews', 'handling_agents', 'passengers', 'accommodations', 'other'] as $key) {
            $data[$key] ??= [];
        }

        return $data;
    }

    private function svgDataUri(string $fileName): ?string
    {
        $path = base_path('frontend/dist/'.$fileName);
        if (! File::exists($path)) {
            return null;
        }

        $contents = File::get($path);
        if ($contents === '') {
            throw new RuntimeException("The FIS report image [$fileName] is empty.");
        }

        return 'data:image/svg+xml;base64,'.base64_encode($contents);
    }
}

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
use Illuminate\Support\Facades\Storage;
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
        $reportSettings = DB::table('fis_report_settings')->where('id', 1)->first();
        $customLogoPath = $reportSettings->logo_path ?? null;
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

        $defaultAircraftImage = match (strtoupper($flight->aircraftRegistration->registration)) {
            'PK-TPE' => 'tpe.svg',
            'PK-TFS' => 'tfs.svg',
            'PK-TPG' => 'tpg.svg',
            default => null,
        };
        $aircraftImage = $flight->aircraftRegistration->report_image_path
            ? $this->storedImageDataUri($flight->aircraftRegistration->report_image_path)
            : ($defaultAircraftImage ? $this->svgDataUri($defaultAircraftImage) : null);

        $pdf = Pdf::loadView('fis::pdf.flight-report', [
            'flight' => $flight,
            'airports' => $airports,
            'generatedAt' => now(),
            'helicopterImage' => $aircraftImage,
            'headerTitle' => $reportSettings->header_title ?? "Flight\nInformation\nSheet",
            'iatLogo' => $customLogoPath
                ? $this->storedImageDataUri($customLogoPath)
                : $this->svgDataUri('logo-iat.svg'),
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

    private function storedImageDataUri(string $path): string
    {
        $disk = Storage::disk('local');
        if (! $disk->exists($path)) {
            throw new RuntimeException('Logo report FIS tidak ditemukan.');
        }

        $mimeType = $disk->mimeType($path);
        if (! in_array($mimeType, ['image/jpeg', 'image/png', 'image/svg+xml'], true)) {
            throw new RuntimeException('Format logo report FIS tidak didukung.');
        }

        $contents = $disk->get($path);
        if ($contents === '') {
            throw new RuntimeException('Logo report FIS kosong.');
        }

        return 'data:'.$mimeType.';base64,'.base64_encode($contents);
    }
}

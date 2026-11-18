<?php

namespace App\Modules\Fis\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class FisSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // Airport snapshot from OurAirports (public domain): large, medium, and small airports.
        DB::table('aircraft_registrations')->upsert([
            ['registration' => 'PK-TFS', 'aircraft_type' => 'LEGACY 600', 'seat_capacity' => 13, 'created_at' => $now, 'updated_at' => $now],
            ['registration' => 'PK-JAM', 'aircraft_type' => 'Hawker 900XP', 'seat_capacity' => 8, 'created_at' => $now, 'updated_at' => $now],
            ['registration' => 'PK-TPG', 'aircraft_type' => 'EC155-B1', 'seat_capacity' => 12, 'created_at' => $now, 'updated_at' => $now],
            ['registration' => 'PK-TPE', 'aircraft_type' => 'EC155-B1', 'seat_capacity' => 12, 'created_at' => $now, 'updated_at' => $now],
        ], ['registration'], ['aircraft_type', 'seat_capacity', 'updated_at']);

        $path = database_path('seeders/data/airports.csv');
        if (! File::exists($path)) {
            throw new RuntimeException("The bundled airport dataset was not found at [$path].");
        }

        $file = fopen($path, 'r');
        if ($file === false) {
            throw new RuntimeException("The bundled airport dataset could not be opened at [$path].");
        }

        try {
            $header = fgetcsv($file, escape: '');
            if ($header === false) {
                throw new RuntimeException('The bundled airport dataset is empty.');
            }

            $batch = [];
            while (($row = fgetcsv($file, escape: '')) !== false) {
                if (count($row) !== count($header)) {
                    throw new RuntimeException('The bundled airport dataset contains an invalid row.');
                }

                $airport = array_combine($header, $row);
                $batch[] = [
                    'ident' => $airport['ident'],
                    'type' => $airport['type'],
                    'name' => $airport['name'],
                    'keywords' => $airport['keywords'] ?: null,
                    'iso_country' => $airport['iso_country'] ?: null,
                    'country_name' => $airport['country_name'] ?: null,
                    'municipality' => $airport['municipality'] ?: null,
                    'icao_code' => $airport['icao_code'] ?: null,
                    'iata_code' => $airport['iata_code'] ?: null,
                    'scheduled_service' => $airport['scheduled_service'] === 'yes',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($batch) === 1000) {
                    $this->upsertAirports($batch);
                    $batch = [];
                }
            }

            if ($batch !== []) {
                $this->upsertAirports($batch);
            }
        } finally {
            fclose($file);
        }
    }

    /** @param list<array<string, mixed>> $airports */
    private function upsertAirports(array $airports): void
    {
        DB::table('fis_airports')->upsert(
            $airports,
            ['ident'],
            [
                'type',
                'name',
                'keywords',
                'iso_country',
                'country_name',
                'municipality',
                'icao_code',
                'iata_code',
                'scheduled_service',
                'updated_at',
            ],
        );
    }
}

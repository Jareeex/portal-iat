<?php

namespace Database\Seeders;

use App\Models\Crew;
use App\Models\TrainingItem;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class CrewTrainingSeeder extends Seeder
{
    public function run(): void
    {
        if (Crew::query()->exists()) {
            return;
        }

        $pilot = 'Pilot';
        $flightAttendant = 'Flight Attendant';
        $operations = 'FOO & HLO';
        $atr = 'Fixed Wing - ATR 42 / EMB 135';
        $emb = 'Fixed Wing - EMB 135 BJ (RSS)';
        $hawker = 'Fixed Wing - Hawker 900XP (JAM)';
        $ec = 'Rotary Wing - EC 155 B1';
        $ops = 'Flight Operations Officer & HLO';

        $rows = [
            ['R.B. Edwin R. Setyonugroho', 'EDW', $pilot, $atr, 'LOA GI, RI & FIS', 9, 1, 5],
            ['Bambang Pramuhardi', 'PRM', $pilot, $atr, 'PIC', 8, 1, 6],
            ['Sukardi', 'SKD', $pilot, $atr, 'GI, FIS, FIA & CCP', 8, 1, 6],
            ['Yudha Mahardhika Mulyadi', 'YUD', $pilot, $atr, 'SIC', 5, 6, 3],
            ['Petrus Febry Susanto', 'FEB', $pilot, $atr, 'LOA GI, RI & FIA', 2, 2, 13],
            ['Ivan Paltak Siregar', 'IPS', $pilot, $atr, 'PIC', 1, 3, 13],
            ['Alexander Farrel Avianto', 'FAR', $pilot, $atr, 'SIC', 1, 7, 8],
            ['Mochamad Brian Luberizky', 'BRI', $pilot, $atr, 'SIC', 5, 2, 9],
            ['Rahmat Nasrul Maulana', 'RNM', $pilot, $atr, 'SIC', 0, 0, 16],
            ['Andy Bambang Soepriyoadi', null, $pilot, $emb, 'PIC', 0, 0, 16],
            ['Andaru Putra Ramadhan', null, $pilot, $emb, 'SIC', 1, 1, 14],
            ['James Anthony Russel', null, $pilot, $hawker, 'PIC', 10, 0, 3],
            ['Andry Kusuma', null, $pilot, $hawker, 'PIC', 6, 5, 5],
            ['Muhammad Aris Pratama', null, $pilot, $hawker, 'PIC', 4, 4, 7],
            ['Alfrance Berardi Achmad Nasotion', null, $pilot, $hawker, 'SIC', 5, 1, 9],
            ['Bambang Hadi', 'HAD', $pilot, $ec, 'PIC', 2, 1, 15],
            ['Priyanto', 'PRI', $pilot, $ec, 'CCP & LOA', 2, 3, 13],
            ['Syahrul Alam', 'RUL', $pilot, $ec, 'CCP & LOA', 2, 4, 12],
            ['Supriadi Talitti', 'SPD', $pilot, $ec, 'PIC', 1, 3, 14],
            ['Hariady Marpaung', 'HRD', $pilot, $ec, 'LOA GI & FIA', 3, 1, 14],
            ['Sugiarto', 'SGT', $pilot, $ec, 'PIC', 3, 2, 13],
            ['Moh. Amin Fahas Setiawan', 'AMN', $pilot, $ec, 'PIC', 4, 3, 11],
            ['Ari Joko Sutrisno', 'ARI', $pilot, $ec, 'PIC', 5, 5, 8],
            ['Fitria Dwi Wahyuni', null, $flightAttendant, 'Flight Attendant', 'LOA GI, CI & CC', 4, 1, 3],
            ['Miquella Andjani', null, $flightAttendant, 'Flight Attendant', 'Cabin Service', 1, 0, 0],
            ['Sartono', null, $operations, $ops, 'FOO, HLO', 11, 0, 1],
            ['Muchsin Nafarin', null, $operations, $ops, 'FOO, HLO', 11, 0, 2],
            ['J. Adjie Polantika', null, $operations, $ops, 'CCFO & LOA', 9, 1, 1],
            ['Maruto Sasongko', null, $operations, $ops, 'FOO, HLO', 9, 0, 1],
            ['Alpin Syamsudin', null, $operations, $ops, 'FOO', 4, 0, 2],
            ['Mulyo Rubiyanto', null, $operations, $ops, 'FOO', 7, 0, 2],
            ['Medhi W. Trisatria', null, $operations, $ops, 'FOO, HLO', 6, 1, 2],
            ['Adri Elias', null, $operations, $ops, 'CCFO & LOA', 6, 1, 2],
            ['Nur Budi Novianto', null, $operations, $ops, 'FOO, HLO', 7, 0, 2],
            ['Muhammad Dadan Ramadhan', null, $operations, $ops, 'FOO', 6, 1, 2],
        ];

        $today = Carbon::today();

        foreach ($rows as $sortOrder => [$name, $code, $category, $fleet, $position, $expired, $soon, $valid]) {
            $crew = Crew::create([
                'name' => $name,
                'code' => $code,
                'category' => $category,
                'fleet' => $fleet,
                'position' => $position,
                'sort_order' => $sortOrder,
            ]);

            $itemNumber = 1;
            $createItems = function (int $count, callable $date) use ($crew, &$itemNumber): void {
                for ($index = 0; $index < $count; $index++) {
                    TrainingItem::create([
                        'crew_id' => $crew->id,
                        'name' => 'Training item '.$itemNumber++,
                        'next_check' => $date($index),
                    ]);
                }
            };

            $createItems($expired, fn (int $index): Carbon => $today->copy()->subDays(10 + $index * 20));
            $createItems($soon, fn (int $index): Carbon => $today->copy()->addDays(7 + $index * 6));
            $createItems($valid, fn (int $index): Carbon => $today->copy()->addDays(90 + $index * 30));
        }
    }
}

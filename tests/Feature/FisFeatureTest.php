<?php

use App\Models\Department;
use App\Models\Page;
use App\Models\User;
use App\Modules\Fis\Database\Seeders\FisSeeder;
use App\Modules\Fis\Models\AircraftRegistration;
use App\Modules\Fis\Models\Flight;
use Laravel\Sanctum\Sanctum;

function createFisPage(): Page
{
    $department = Department::create([
        'name' => 'Marketing',
        'code' => 'MKT',
        'has_full_access' => false,
    ]);

    return Page::create([
        'department_id' => $department->id,
        'key' => 'fis',
        'name' => 'FIS',
        'route' => '/fis',
        'is_active' => true,
    ]);
}

function createFisAircraft(int $capacity = 2): AircraftRegistration
{
    return AircraftRegistration::create([
        'registration' => 'PK-FIS',
        'aircraft_type' => 'Legacy 600',
        'seat_capacity' => $capacity,
    ]);
}

function fisFlightPayload(AircraftRegistration $aircraft, array $passengers = []): array
{
    return [
        'flight_no' => 'IAT-101',
        'date' => '2026-11-16',
        'chartered_by' => 'Portal Test',
        'aircraft_registration_id' => $aircraft->id,
        'schedules' => [[
            'date' => '2026-11-16',
            'from' => 'cgk',
            'to' => 'dps',
            'etd' => '2026-11-16T08:00',
            'eta' => '2026-11-16T09:00',
            'remarks' => '',
        ]],
        'passengers' => $passengers,
    ];
}

test('master can create and update flight information', function () {
    createFisPage();
    $aircraft = createFisAircraft();
    $master = User::factory()->create([
        'role' => User::ROLE_MASTER,
        'is_active' => true,
    ]);
    Sanctum::actingAs($master);

    $this->getJson('/api/fis/aircraft')
        ->assertOk()
        ->assertJsonPath('0.registration', 'PK-FIS');

    $created = $this->postJson('/api/fis/flights', fisFlightPayload($aircraft))
        ->assertCreated()
        ->assertJsonPath('data.schedules.0.from', 'CGK')
        ->assertJsonPath('data.schedules.0.duration_minutes', 60)
        ->json('data');

    $report = $this->get("/api/fis/flights/{$created['id']}/report")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->getContent();
    expect($report)->toStartWith('%PDF-');

    $this->getJson('/api/fis/flights')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);

    $this->putJson("/api/fis/flights/{$created['id']}", [
        ...fisFlightPayload($aircraft),
        'flight_no' => 'IAT-102',
    ])->assertOk()->assertJsonPath('data.flight_no', 'IAT-102');

    $this->deleteJson("/api/fis/flights/{$created['id']}")->assertNoContent();
    expect(Flight::find($created['id']))->toBeNull();
});

test('marketing users need a grant and view-only access cannot edit FIS', function () {
    $page = createFisPage();
    createFisAircraft();
    $user = User::factory()->create(['is_active' => true]);
    Sanctum::actingAs($user);

    $this->getJson('/api/fis/flights')->assertForbidden();

    $user->pages()->attach($page, ['access_level' => 'view']);
    $this->getJson('/api/fis/flights')->assertOk();
    $this->postJson('/api/fis/flights', [])->assertForbidden();
});

test('flight validation enforces arrival time and aircraft seat capacity', function () {
    createFisPage();
    $aircraft = createFisAircraft(capacity: 1);
    $master = User::factory()->create([
        'role' => User::ROLE_MASTER,
        'is_active' => true,
    ]);
    Sanctum::actingAs($master);

    $this->postJson('/api/fis/flights', [
        ...fisFlightPayload($aircraft, [['name' => 'Passenger One'], ['name' => 'Passenger Two']]),
        'schedules' => [[
            'date' => '2026-11-16',
            'from' => 'CGK',
            'to' => 'DPS',
            'etd' => '2026-11-16T09:00',
            'eta' => '2026-11-16T08:00',
        ]],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['schedules.0.eta', 'passengers']);
});

test('airport search finds local, international, and country-wide airports', function () {
    createFisPage();
    $master = User::factory()->create([
        'role' => User::ROLE_MASTER,
        'is_active' => true,
    ]);
    app(FisSeeder::class)->run();
    Sanctum::actingAs($master);

    $this->getJson('/api/fis/airports?q=Banyuwangi')
        ->assertOk()
        ->assertJsonFragment(['iata_code' => 'BWX', 'name' => 'Banyuwangi Airport']);
    $this->getJson('/api/fis/airports?q=Los Angeles')
        ->assertOk()
        ->assertJsonFragment(['iata_code' => 'LAX', 'name' => 'Los Angeles International Airport']);
    $this->getJson('/api/fis/airports?q=Finland')
        ->assertOk()
        ->assertJsonFragment([
            'municipality' => 'Helsinki (Vantaa)',
            'country_name' => 'Finland',
        ]);
    $this->getJson('/api/fis/airports?q=Joko%20Tole')
        ->assertOk()
        ->assertJsonFragment([
            'name' => 'Trunojoyo Airport',
            'iata_code' => 'SUP',
            'icao_code' => 'WART',
        ]);
});

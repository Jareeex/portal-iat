<?php

use App\Models\Department;
use App\Models\Page;
use App\Models\User;
use App\Modules\Fis\Database\Seeders\FisSeeder;
use App\Modules\Fis\Models\AircraftRegistration;
use App\Modules\Fis\Models\Flight;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

test('flight report download filename removes path separators from flight number', function () {
    createFisPage();
    $aircraft = createFisAircraft();
    $master = User::factory()->create([
        'role' => User::ROLE_MASTER,
        'is_active' => true,
    ]);
    Sanctum::actingAs($master);

    $flight = $this->postJson('/api/fis/flights', [
        ...fisFlightPayload($aircraft),
        'flight_no' => 'IAT/2026\\01',
    ])->assertCreated()->json('data');

    $report = $this->get("/api/fis/flights/{$flight['id']}/report")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $contentDisposition = $report->headers->get('content-disposition');
    expect($contentDisposition)
        ->toContain('flight-information-iat-2026-01-')
        ->not->toContain('/')
        ->not->toContain('\\');
});

test('master can update the FIS PDF header and restore the default logo', function () {
    Storage::fake('local');
    createFisPage();
    $aircraft = createFisAircraft();
    $master = User::factory()->create([
        'role' => User::ROLE_MASTER,
        'is_active' => true,
    ]);
    Sanctum::actingAs($master);

    $this->getJson('/api/master/fis-report-settings')
        ->assertOk()
        ->assertJsonPath('header_title', "Flight\nInformation\nSheet")
        ->assertJsonPath('has_custom_logo', false);

    $this->post('/api/master/fis-report-settings', [
        'header_title' => 'Operations Report',
        'logo' => UploadedFile::fake()->image('custom-logo.png'),
        'remove_logo' => '0',
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('header_title', 'Operations Report')
        ->assertJsonPath('has_custom_logo', true);

    $logoPath = collect(Storage::disk('local')->allFiles('fis/report'))->first();
    expect($logoPath)->not->toBeNull();

    $this->get('/api/master/fis-report-settings/logo')
        ->assertOk()
        ->assertHeader('content-type', 'image/png');

    $flight = $this->postJson('/api/fis/flights', fisFlightPayload($aircraft))
        ->assertCreated()
        ->json('data');
    $this->get("/api/fis/flights/{$flight['id']}/report")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $this->postJson('/api/master/fis-report-settings', [
        'header_title' => "Flight\nInformation\nSheet",
        'remove_logo' => true,
    ])->assertOk()->assertJsonPath('has_custom_logo', false);

    Storage::disk('local')->assertMissing($logoPath);
});

test('only master can change FIS PDF header settings and logo uploads must be images', function () {
    createFisPage();
    $aircraft = createFisAircraft();
    $user = User::factory()->create(['is_active' => true]);
    Sanctum::actingAs($user);

    $this->getJson('/api/master/fis-report-settings')->assertForbidden();
    $this->getJson('/api/master/fis-report-settings/aircraft')->assertForbidden();
    $this->postJson('/api/master/fis-report-settings', [
        'header_title' => 'Not allowed',
    ])->assertForbidden();
    $this->postJson('/api/master/fis-report-settings/aircraft/'.$aircraft->id.'/image', [])
        ->assertForbidden();

    $master = User::factory()->create([
        'role' => User::ROLE_MASTER,
        'is_active' => true,
    ]);
    Sanctum::actingAs($master);

    $this->post('/api/master/fis-report-settings', [
        'header_title' => 'Operations Report',
        'logo' => UploadedFile::fake()->create('invalid.svg', 10, 'image/svg+xml'),
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('logo');

    $this->post('/api/master/fis-report-settings', [
        'header_title' => 'Operations Report',
        'logo' => UploadedFile::fake()->createWithContent(
            'unsafe.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
        ),
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('logo');
});

test('master can set and reset the PDF image for each registered aircraft', function () {
    Storage::fake('local');
    createFisPage();
    $aircraft = createFisAircraft();
    $master = User::factory()->create([
        'role' => User::ROLE_MASTER,
        'is_active' => true,
    ]);
    Sanctum::actingAs($master);

    $this->getJson('/api/master/fis-report-settings/aircraft')
        ->assertOk()
        ->assertJsonPath('0.registration', 'PK-FIS')
        ->assertJsonPath('0.has_report_image', false);

    $this->post('/api/master/fis-report-settings/aircraft/'.$aircraft->id.'/image', [
        'image' => UploadedFile::fake()->image('pk-fis.jpg'),
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('id', $aircraft->id)
        ->assertJsonPath('has_report_image', true);

    $imagePath = $aircraft->fresh()->report_image_path;
    expect($imagePath)->not->toBeNull();
    Storage::disk('local')->assertExists($imagePath);

    $flight = $this->postJson('/api/fis/flights', fisFlightPayload($aircraft))
        ->assertCreated()
        ->json('data');
    $this->get("/api/fis/flights/{$flight['id']}/report")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $this->deleteJson('/api/master/fis-report-settings/aircraft/'.$aircraft->id.'/image')
        ->assertOk()
        ->assertJsonPath('has_report_image', false);

    Storage::disk('local')->assertMissing($imagePath);
    expect($aircraft->fresh()->report_image_path)->toBeNull();
});

test('master can use transparent SVG images for the report logo and registered aircraft', function () {
    Storage::fake('local');
    createFisPage();
    $aircraft = createFisAircraft();
    $master = User::factory()->create([
        'role' => User::ROLE_MASTER,
        'is_active' => true,
    ]);
    Sanctum::actingAs($master);
    $svg = UploadedFile::fake()->createWithContent(
        'transparent-logo.svg',
        '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="50" viewBox="0 0 100 50"><path fill="#123456" d="M0 0h100v50H0z"/></svg>',
    );

    $this->post('/api/master/fis-report-settings', [
        'header_title' => 'Flight Report',
        'logo' => $svg,
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('has_custom_logo', true);

    $this->get('/api/master/fis-report-settings/logo')
        ->assertOk()
        ->assertHeader('content-type', 'image/svg+xml');

    $this->post('/api/master/fis-report-settings/aircraft/'.$aircraft->id.'/image', [
        'image' => UploadedFile::fake()->createWithContent(
            'aircraft.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="50" viewBox="0 0 100 50"><path fill="#654321" d="M0 0h100v50H0z"/></svg>',
        ),
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('has_report_image', true);

    $flight = $this->postJson('/api/fis/flights', fisFlightPayload($aircraft))
        ->assertCreated()
        ->json('data');
    $this->get("/api/fis/flights/{$flight['id']}/report")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('master can upload WebP images even when the file is named as JPG', function () {
    Storage::fake('local');
    createFisPage();
    $aircraft = createFisAircraft();
    $master = User::factory()->create([
        'role' => User::ROLE_MASTER,
        'is_active' => true,
    ]);
    Sanctum::actingAs($master);

    $webp = UploadedFile::fake()->image('source.webp');
    $webpContents = file_get_contents($webp->getPathname());
    expect($webpContents)->not->toBeFalse();

    $this->post('/api/master/fis-report-settings', [
        'header_title' => 'Flight Report',
        'logo' => UploadedFile::fake()
            ->createWithContent('logo.jpg', $webpContents)
            ->mimeType('image/webp'),
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('has_custom_logo', true);

    $logoPath = DB::table('fis_report_settings')->value('logo_path');
    expect($logoPath)->toEndWith('.png');
    Storage::disk('local')->assertExists($logoPath);

    $this->post('/api/master/fis-report-settings/aircraft/'.$aircraft->id.'/image', [
        'image' => UploadedFile::fake()
            ->createWithContent('aircraft.jpg', $webpContents)
            ->mimeType('image/webp'),
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('has_report_image', true);

    $imagePath = $aircraft->fresh()->report_image_path;
    expect($imagePath)->toEndWith('.png');
    Storage::disk('local')->assertExists($imagePath);
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

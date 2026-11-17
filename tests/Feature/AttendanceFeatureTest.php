<?php

use App\Models\Department;
use App\Models\Page;
use App\Models\User;
use App\Modules\Hr\Models\Attendance;
use App\Services\AccessService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/**
 * @return array{
 *     photo: UploadedFile,
 *     latitude: float,
 *     longitude: float,
 *     reason?: string,
 *     reason_details?: string
 * }
 */
function hrAttendancePayload(
    string $photoName,
    float $latitude = -6.267306,
    float $longitude = 106.896514,
    ?string $reason = null,
    ?string $reasonDetails = null,
): array {
    $payload = [
        'photo' => UploadedFile::fake()->image($photoName),
        'latitude' => $latitude,
        'longitude' => $longitude,
    ];

    if ($reason !== null) {
        $payload['reason'] = $reason;
    }

    if ($reasonDetails !== null) {
        $payload['reason_details'] = $reasonDetails;
    }

    return $payload;
}

test('users clock in and out with a new photo and server-recorded time', function () {
    Storage::fake('local');
    $user = User::factory()->create(['is_active' => true]);
    $this->travelTo(Carbon::parse('2026-11-16 08:15:00', 'Asia/Jakarta'));
    Sanctum::actingAs($user);

    $this->post('/api/hr/attendance/clock-in', hrAttendancePayload('clock-in.jpg'), ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.attendance_date', '2026-11-16')
        ->assertJsonPath('data.clock_in_photo_available', true)
        ->assertJsonPath('data.clock_in_is_offsite', false)
        ->assertJsonPath('data.clock_in_distance_meters', 0)
        ->assertJsonPath('data.clock_out_at', null);

    $attendance = Attendance::query()->firstOrFail();
    expect($attendance->clock_in_at->copy()->setTimezone('Asia/Jakarta')->format('H:i'))
        ->toBe('08:15');
    Storage::disk('local')->assertExists($attendance->clock_in_photo_path);

    $this->post('/api/hr/attendance/clock-in', hrAttendancePayload('duplicate.jpg'), ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('attendance');

    $this->travelTo(Carbon::parse('2026-11-16 17:05:00', 'Asia/Jakarta'));
    $this->post('/api/hr/attendance/clock-out', hrAttendancePayload('clock-out.jpg'), ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.clock_out_photo_available', true)
        ->assertJsonPath('data.clock_out_is_offsite', false);

    $attendance->refresh();
    expect($attendance->clock_out_at?->copy()->setTimezone('Asia/Jakarta')->format('H:i'))
        ->toBe('17:05');
    Storage::disk('local')->assertExists($attendance->clock_out_photo_path);

    $this->getJson('/api/hr/attendance?date=2026-11-16')
        ->assertOk()
        ->assertJsonPath('records.total', 1)
        ->assertJsonPath('records.data.0.id', $attendance->id)
        ->assertJsonPath('geofence.latitude', -6.267306)
        ->assertJsonPath('geofence.longitude', 106.896514)
        ->assertJsonPath('geofence.radius_meters', 300)
        ->assertJsonPath('geofence.on_leave_enabled', false)
        ->assertJsonMissingPath('records.data.0.clock_in_photo_path');

    $this->get("/api/hr/attendance/{$attendance->id}/photo/clock-in")
        ->assertOk()
        ->assertHeader('cache-control', 'no-store, private');
});

test('users cannot clock out before clocking in or access another users photo', function () {
    Storage::fake('local');
    $owner = User::factory()->create(['is_active' => true]);
    $otherUser = User::factory()->create(['is_active' => true]);
    Sanctum::actingAs($owner);

    $this->post('/api/hr/attendance/clock-out', hrAttendancePayload('clock-out.jpg'), ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('attendance');

    $this->travelTo(Carbon::parse('2026-11-16 08:15:00', 'Asia/Jakarta'));
    $this->post('/api/hr/attendance/clock-in', hrAttendancePayload('clock-in.jpg'), ['Accept' => 'application/json'])
        ->assertCreated();
    $attendance = Attendance::query()->firstOrFail();

    Sanctum::actingAs($otherUser);
    $this->get("/api/hr/attendance/{$attendance->id}/photo/clock-in")->assertForbidden();
    $this->getJson('/api/hr/attendance?date=2026-11-16')
        ->assertOk()
        ->assertJsonCount(0, 'records.data');
});

test('master and employee page editors can correct attendance times', function () {
    Storage::fake('local');
    $department = Department::create([
        'name' => 'Human Resources',
        'code' => 'HR',
        'has_full_access' => false,
    ]);
    $page = Page::create([
        'department_id' => $department->id,
        'key' => 'hr-employees',
        'name' => 'Employees',
        'route' => '/hr/employees',
        'is_active' => true,
    ]);
    $employee = User::factory()->create(['is_active' => true]);
    $editor = User::factory()->create([
        'department_id' => $department->id,
        'is_active' => true,
    ]);
    app(AccessService::class)->sync($editor, [$page->id], [$page->id]);
    $this->travelTo(Carbon::parse('2026-11-16 08:15:00', 'Asia/Jakarta'));

    Sanctum::actingAs($employee);
    $this->post('/api/hr/attendance/clock-in', hrAttendancePayload('clock-in.jpg'), ['Accept' => 'application/json'])
        ->assertCreated();
    $attendance = Attendance::query()->firstOrFail();

    Sanctum::actingAs($editor);
    $this->getJson('/api/hr/attendance?date=2026-11-16')
        ->assertOk()
        ->assertJsonPath('records.total', 1);
    $this->putJson("/api/hr/attendance/{$attendance->id}", [
        'clock_in_time' => '08:30',
        'clock_out_time' => '17:00',
    ])->assertOk()
        ->assertJsonPath('data.clock_in_at', '2026-11-16T01:30:00+00:00')
        ->assertJsonPath('data.clock_out_at', '2026-11-16T10:00:00+00:00');

    $viewer = User::factory()->create(['is_active' => true]);
    Sanctum::actingAs($viewer);
    $this->putJson("/api/hr/attendance/{$attendance->id}", [
        'clock_in_time' => '08:30',
        'clock_out_time' => '17:00',
    ])->assertForbidden();

    $master = User::factory()->create([
        'role' => User::ROLE_MASTER,
        'is_active' => true,
    ]);
    Sanctum::actingAs($master);
    $this->putJson("/api/hr/attendance/{$attendance->id}", [
        'clock_in_time' => '08:45',
        'clock_out_time' => '17:15',
    ])->assertOk();
});

test('attendance correction rejects a clock-out time before clock-in', function () {
    Storage::fake('local');
    $user = User::factory()->create(['is_active' => true]);
    $master = User::factory()->create([
        'role' => User::ROLE_MASTER,
        'is_active' => true,
    ]);
    $this->travelTo(Carbon::parse('2026-11-16 08:15:00', 'Asia/Jakarta'));
    Sanctum::actingAs($user);
    $this->post('/api/hr/attendance/clock-in', hrAttendancePayload('clock-in.jpg'), ['Accept' => 'application/json'])
        ->assertCreated();
    $attendance = Attendance::query()->firstOrFail();

    Sanctum::actingAs($master);
    $this->putJson("/api/hr/attendance/{$attendance->id}", [
        'clock_in_time' => '09:00',
        'clock_out_time' => '08:59',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('clock_out_time');
});

test('master can enable or disable On Leave for an individual account', function () {
    $master = User::factory()->create([
        'role' => User::ROLE_MASTER,
        'is_active' => true,
    ]);
    $user = User::factory()->create(['is_active' => true]);
    Sanctum::actingAs($master);

    $this->patchJson("/api/master/users/{$user->id}/on-leave", [
        'on_leave_enabled' => true,
    ])->assertOk()
        ->assertJsonPath('on_leave_enabled', true);

    expect($user->fresh()->on_leave_enabled)->toBeTrue();

    Sanctum::actingAs($user);
    $this->patchJson("/api/master/users/{$user->id}/on-leave", [
        'on_leave_enabled' => false,
    ])->assertForbidden();
});

test('HR and Master can update the attendance radius', function () {
    $department = Department::create([
        'name' => 'Human Resources',
        'code' => 'HR',
        'has_full_access' => false,
    ]);
    $page = Page::create([
        'department_id' => $department->id,
        'key' => 'hr-employees',
        'name' => 'Employees',
        'route' => '/hr/employees',
        'is_active' => true,
    ]);
    $hrEditor = User::factory()->create([
        'department_id' => $department->id,
        'is_active' => true,
    ]);
    app(AccessService::class)->sync($hrEditor, [$page->id], [$page->id]);
    $master = User::factory()->create([
        'role' => User::ROLE_MASTER,
        'is_active' => true,
    ]);
    $viewer = User::factory()->create(['is_active' => true]);

    Sanctum::actingAs($hrEditor);
    $this->putJson('/api/hr/attendance/settings', [
        'radius_meters' => 500,
    ])->assertOk()
        ->assertJsonPath('data.radius_meters', 500);

    Sanctum::actingAs($viewer);
    $this->putJson('/api/hr/attendance/settings', [
        'radius_meters' => 750,
    ])->assertForbidden();

    Sanctum::actingAs($master);
    $this->putJson('/api/hr/attendance/settings', [
        'radius_meters' => 1000,
    ])->assertOk()
        ->assertJsonPath('data.radius_meters', 1000);
});

test('attendance requires valid location coordinates', function () {
    Storage::fake('local');
    $user = User::factory()->create(['is_active' => true]);
    Sanctum::actingAs($user);

    $this->post('/api/hr/attendance/clock-in', [
        'photo' => UploadedFile::fake()->image('clock-in.jpg'),
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['latitude', 'longitude']);

    expect(Attendance::query()->count())->toBe(0);
});

test('the 300 meter geofence accepts nearby check-ins and rejects distant ones', function () {
    Storage::fake('local');
    $nearbyUser = User::factory()->create(['is_active' => true]);
    $distantUser = User::factory()->create(['is_active' => true]);

    Sanctum::actingAs($nearbyUser);
    $this->post('/api/hr/attendance/clock-in', hrAttendancePayload(
        'nearby.jpg',
        -6.264612,
        106.896514,
    ), ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.clock_in_is_offsite', false)
        ->assertJsonPath('data.clock_in_distance_meters', 300);

    Sanctum::actingAs($distantUser);
    $this->post('/api/hr/attendance/clock-in', hrAttendancePayload(
        'distant.jpg',
        -6.264603,
        106.896514,
    ), ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('latitude');

    expect(Attendance::query()->count())->toBe(1);
});

test('On Leave is set automatically for offsite clock-in and requires a reason', function () {
    Storage::fake('local');
    $user = User::factory()->create([
        'is_active' => true,
        'on_leave_enabled' => true,
    ]);
    Sanctum::actingAs($user);

    $this->post('/api/hr/attendance/clock-in', hrAttendancePayload(
        'missing-reason.jpg',
        -6.267306,
        106.900514,
    ), ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reason');

    expect(Attendance::query()->count())->toBe(0);

    $this->post('/api/hr/attendance/clock-in', hrAttendancePayload(
        'meeting.jpg',
        -6.267306,
        106.900514,
        'meeting',
    ), ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.clock_in_is_offsite', true)
        ->assertJsonPath('data.clock_in_reason', 'meeting');

    expect(Attendance::query()->firstOrFail()->clock_in_is_offsite)->toBeTrue();
});

test('offsite custom reasons require a note', function () {
    Storage::fake('local');
    $user = User::factory()->create([
        'is_active' => true,
        'on_leave_enabled' => true,
    ]);
    Sanctum::actingAs($user);

    $this->post('/api/hr/attendance/clock-in', hrAttendancePayload(
        'other-without-note.jpg',
        -6.267306,
        106.900514,
        'other',
    ), ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reason_details');

    $this->post('/api/hr/attendance/clock-in', hrAttendancePayload(
        'other-with-note.jpg',
        -6.267306,
        106.900514,
        'other',
        'Presentasi vendor',
    ), ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.clock_in_reason_details', 'Presentasi vendor');
});

test('clock-out also checks and records the offsite location', function () {
    Storage::fake('local');
    $user = User::factory()->create([
        'is_active' => true,
        'on_leave_enabled' => true,
    ]);
    $this->travelTo(Carbon::parse('2026-11-16 08:15:00', 'Asia/Jakarta'));
    Sanctum::actingAs($user);

    $this->post('/api/hr/attendance/clock-in', hrAttendancePayload('clock-in.jpg'), [
        'Accept' => 'application/json',
    ])->assertCreated();

    $this->travelTo(Carbon::parse('2026-11-16 17:05:00', 'Asia/Jakarta'));
    $this->post('/api/hr/attendance/clock-out', hrAttendancePayload(
        'clock-out-offsite.jpg',
        -6.267306,
        106.900514,
        'dinas_luar',
    ), ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.clock_out_is_offsite', true)
        ->assertJsonPath('data.clock_out_reason', 'dinas_luar');
});

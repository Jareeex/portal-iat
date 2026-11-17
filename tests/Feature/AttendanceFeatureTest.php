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

test('users clock in and out with a new photo and server-recorded time', function () {
    Storage::fake('local');
    $user = User::factory()->create(['is_active' => true]);
    $this->travelTo(Carbon::parse('2026-11-16 08:15:00', 'Asia/Jakarta'));
    Sanctum::actingAs($user);

    $this->post('/api/hr/attendance/clock-in', [
        'photo' => UploadedFile::fake()->image('clock-in.jpg'),
    ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.attendance_date', '2026-11-16')
        ->assertJsonPath('data.clock_in_photo_available', true)
        ->assertJsonPath('data.clock_out_at', null);

    $attendance = Attendance::query()->firstOrFail();
    expect($attendance->clock_in_at->copy()->setTimezone('Asia/Jakarta')->format('H:i'))
        ->toBe('08:15');
    Storage::disk('local')->assertExists($attendance->clock_in_photo_path);

    $this->post('/api/hr/attendance/clock-in', [
        'photo' => UploadedFile::fake()->image('duplicate.jpg'),
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('attendance');

    $this->travelTo(Carbon::parse('2026-11-16 17:05:00', 'Asia/Jakarta'));
    $this->post('/api/hr/attendance/clock-out', [
        'photo' => UploadedFile::fake()->image('clock-out.jpg'),
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.clock_out_photo_available', true);

    $attendance->refresh();
    expect($attendance->clock_out_at?->copy()->setTimezone('Asia/Jakarta')->format('H:i'))
        ->toBe('17:05');
    Storage::disk('local')->assertExists($attendance->clock_out_photo_path);

    $this->getJson('/api/hr/attendance?date=2026-11-16')
        ->assertOk()
        ->assertJsonPath('records.total', 1)
        ->assertJsonPath('records.data.0.id', $attendance->id)
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

    $this->post('/api/hr/attendance/clock-out', [
        'photo' => UploadedFile::fake()->image('clock-out.jpg'),
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('attendance');

    $this->travelTo(Carbon::parse('2026-11-16 08:15:00', 'Asia/Jakarta'));
    $this->post('/api/hr/attendance/clock-in', [
        'photo' => UploadedFile::fake()->image('clock-in.jpg'),
    ], ['Accept' => 'application/json'])->assertCreated();
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
    $this->post('/api/hr/attendance/clock-in', [
        'photo' => UploadedFile::fake()->image('clock-in.jpg'),
    ], ['Accept' => 'application/json'])->assertCreated();
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
    $this->post('/api/hr/attendance/clock-in', [
        'photo' => UploadedFile::fake()->image('clock-in.jpg'),
    ], ['Accept' => 'application/json'])->assertCreated();
    $attendance = Attendance::query()->firstOrFail();

    Sanctum::actingAs($master);
    $this->putJson("/api/hr/attendance/{$attendance->id}", [
        'clock_in_time' => '09:00',
        'clock_out_time' => '08:59',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('clock_out_time');
});

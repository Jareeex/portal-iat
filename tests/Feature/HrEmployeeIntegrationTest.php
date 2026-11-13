<?php

use App\Models\Department;
use App\Models\Page;
use App\Models\User;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\JobPosition;
use App\Services\AccessService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

test('every account has one employee profile and the directory includes master employees', function () {
    Page::create([
        'key' => 'hr-employees',
        'name' => 'Employees',
        'route' => '/hr/employees',
        'is_active' => true,
    ]);

    $master = User::factory()->create(['role' => User::ROLE_MASTER, 'is_active' => true]);
    $user = User::factory()->create(['is_active' => true]);

    expect($master->employee)->not->toBeNull()
        ->and($user->employee)->not->toBeNull()
        ->and($master->employee->user_id)->toBe($master->id)
        ->and($user->employee->user_id)->toBe($user->id);

    Sanctum::actingAs($master);

    $response = $this->getJson('/api/hr/employees')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonMissingPath('data.0.user.email')
        ->assertJsonMissingPath('data.1.user.email');

    expect(collect($response->json('data'))->pluck('name')->all())
        ->toContain($master->name, $user->name);
});

test('users with employee page access can view the directory but cannot edit profiles', function () {
    $department = Department::create([
        'name' => 'Human Resources',
        'code' => 'HR',
        'has_full_access' => false,
    ]);

    Page::create([
        'department_id' => $department->id,
        'key' => 'hr-employees',
        'name' => 'Employees',
        'route' => '/hr/employees',
        'is_active' => true,
    ]);

    $master = User::factory()->create(['role' => User::ROLE_MASTER, 'is_active' => true]);
    $hrUser = User::factory()->create([
        'role' => User::ROLE_USER,
        'department_id' => $department->id,
        'is_active' => true,
    ]);

    Sanctum::actingAs($hrUser);

    $this->getJson('/api/hr/employees')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['name' => $master->name]);

    $this->putJson("/api/hr/employees/{$hrUser->employee->id}", [
        'job_title' => 'HR Specialist',
    ])->assertForbidden();
});

test('master updates employee profile without changing the linked user account', function () {
    JobPosition::create(['name' => 'Pilot']);
    $master = User::factory()->create(['role' => User::ROLE_MASTER, 'is_active' => true]);
    $user = User::factory()->create(['name' => 'Original Name', 'is_active' => true]);

    Sanctum::actingAs($master);

    expect($master->isMaster())->toBeTrue();

    $response = $this->putJson("/api/hr/employees/{$user->employee->id}", [
        'name' => 'Different Account Name',
        'job_title' => 'Pilot',
        'employee_number' => 'IAT-1001',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.job_title', 'Pilot')
        ->assertJsonPath('data.employee_number', 'IAT-1001');

    expect($user->fresh()->name)->toBe('Original Name')
        ->and($user->employee->fresh()->job_title)->toBe('Pilot');
});

test('master can assign an employee editor who can update employee records', function () {
    JobPosition::create(['name' => 'Training Editor']);
    $hrPage = Page::create([
        'key' => 'hr-employees',
        'name' => 'Employees',
        'route' => '/hr/employees',
        'is_active' => true,
    ]);
    $master = User::factory()->create(['role' => User::ROLE_MASTER, 'is_active' => true]);
    $editor = User::factory()->create(['is_active' => true]);
    $employee = User::factory()->create()->employee;

    Sanctum::actingAs($master);
    $this->putJson("/api/master/users/{$editor->id}/access", [
        'page_ids' => [$hrPage->id],
        'edit_page_ids' => [$hrPage->id],
    ])->assertOk();

    Sanctum::actingAs($editor);
    $this->putJson("/api/hr/employees/{$employee->id}", [
        'job_title' => 'Training Editor',
    ])->assertOk()
        ->assertJsonPath('data.job_title', 'Training Editor');
});

test('employee editors can create employees without accounts but viewers cannot', function () {
    JobPosition::create(['name' => 'HR Specialist']);
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
    $editor = User::factory()->create([
        'department_id' => $department->id,
        'is_active' => true,
    ]);
    app(AccessService::class)->sync($editor, [$page->id], [$page->id]);

    Sanctum::actingAs($editor);

    $response = $this->postJson('/api/hr/employees', [
        'name' => 'New Employee',
        'employee_number' => 'IAT-2001',
        'job_title' => 'HR Specialist',
        'employment_status' => 'Tetap',
    ])->assertCreated()
        ->assertJsonPath('data.name', 'New Employee')
        ->assertJsonPath('data.user', null)
        ->assertJsonPath('data.employee_number', 'IAT-2001');

    $employeeId = $response->json('data.id');
    expect(Employee::findOrFail($employeeId)->user_id)->toBeNull();

    $this->putJson("/api/hr/employees/{$employeeId}", [
        'name' => 'Updated Employee',
    ])->assertOk()->assertJsonPath('data.name', 'Updated Employee');

    $viewer = User::factory()->create([
        'department_id' => $department->id,
        'is_active' => true,
    ]);
    Sanctum::actingAs($viewer);

    $this->postJson('/api/hr/employees', [
        'name' => 'Unauthorized Employee',
    ])->assertForbidden();
    $this->getJson('/api/hr/employees/unlinked')->assertForbidden();
    $this->postJson('/api/master/users', [
        'name' => 'Unauthorized Account',
        'email' => 'unauthorized.account@example.com',
        'password' => 'password123',
    ])->assertForbidden();
});

test('master can upload photos when creating and updating employee profiles', function () {
    Storage::fake('public');
    $master = User::factory()->create([
        'role' => User::ROLE_MASTER,
        'is_active' => true,
    ]);

    Sanctum::actingAs($master);

    $response = $this->post('/api/hr/employees', [
        'name' => 'Photo Employee',
        'photo' => UploadedFile::fake()->image('employee.jpeg'),
    ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonStructure(['data' => ['photo_url']]);

    $employee = Employee::findOrFail($response->json('data.id'));
    $firstPhotoPath = $employee->photo_path;
    expect($response->json('data.photo_url'))->toBe('/storage/'.$firstPhotoPath);
    Storage::disk('public')->assertExists($firstPhotoPath);

    $this->post("/api/hr/employees/{$employee->id}", [
        '_method' => 'PUT',
        'photo' => UploadedFile::fake()->image('updated-employee.jpeg'),
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonStructure(['data' => ['photo_url']]);

    $employee->refresh();
    expect($employee->photo_path)->not->toBe($firstPhotoPath);
    Storage::disk('public')->assertMissing($firstPhotoPath);
    Storage::disk('public')->assertExists($employee->photo_path);
});

test('master can link an employee profile when creating its account', function () {
    JobPosition::create(['name' => 'Pilot']);
    $master = User::factory()->create([
        'role' => User::ROLE_MASTER,
        'is_active' => true,
    ]);
    Sanctum::actingAs($master);

    $employee = $this->postJson('/api/hr/employees', [
        'name' => 'Linked Employee',
        'employee_number' => 'IAT-3001',
        'job_title' => 'Pilot',
    ])->assertCreated()->json('data');

    $user = $this->postJson('/api/master/users', [
        'name' => 'Linked Employee',
        'email' => 'linked.employee@example.com',
        'password' => 'password123',
        'employee_id' => $employee['id'],
    ])->assertCreated()->json();

    expect(Employee::where('user_id', $user['id'])->count())->toBe(1)
        ->and(Employee::where('user_id', $user['id'])->value('id'))->toBe($employee['id'])
        ->and(Employee::where('user_id', $user['id'])->value('employee_number'))->toBe('IAT-3001');
});

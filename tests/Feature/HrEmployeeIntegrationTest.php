<?php

use App\Models\Department;
use App\Models\Page;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('every account has one employee profile and the directory includes master accounts', function () {
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

    $this->getJson('/api/hr/employees')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['name' => $master->name, 'role' => User::ROLE_MASTER])
        ->assertJsonFragment(['name' => $user->name, 'role' => User::ROLE_USER]);
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
        ->assertJsonFragment(['name' => $master->name, 'role' => User::ROLE_MASTER]);

    $this->putJson("/api/hr/employees/{$hrUser->employee->id}", [
        'job_title' => 'HR Specialist',
    ])->assertForbidden();
});

test('master updates employee profile without changing the linked user account', function () {
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
        ->assertJsonPath('data.user.name', 'Original Name')
        ->assertJsonPath('data.job_title', 'Pilot')
        ->assertJsonPath('data.employee_number', 'IAT-1001');

    expect($user->fresh()->name)->toBe('Original Name')
        ->and($user->employee->fresh()->job_title)->toBe('Pilot');
});

test('master can assign an employee editor who can update employee records', function () {
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

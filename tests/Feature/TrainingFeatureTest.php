<?php

use App\Models\Crew;
use App\Models\Department;
use App\Models\Page;
use App\Models\TrainingItem;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('users with training page access can view crew training status', function () {
    $department = Department::create([
        'name' => 'Training',
        'code' => 'TR',
        'has_full_access' => false,
    ]);

    Page::create([
        'department_id' => $department->id,
        'key' => 'training',
        'name' => 'Training',
        'route' => '/training',
        'is_active' => true,
    ]);

    $user = User::create([
        'name' => 'Training User',
        'email' => 'training-user@example.test',
        'password' => 'password',
        'role' => User::ROLE_USER,
        'department_id' => $department->id,
        'is_active' => true,
    ]);

    $crew = Crew::create([
        'name' => 'Test Crew',
        'code' => 'TST',
        'category' => 'Pilot',
        'fleet' => 'Fixed Wing',
        'position' => 'PIC',
    ]);

    TrainingItem::create([
        'crew_id' => $crew->id,
        'name' => 'Annual check',
        'next_check' => now()->addDays(30)->toDateString(),
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/training')
        ->assertOk()
        ->assertJsonPath('summary.crew', 1)
        ->assertJsonPath('summary.soon', 1)
        ->assertJsonPath('crews.0.items.0.name', 'Annual check')
        ->assertJsonPath('crews.0.items.0.status', 'soon');
});

test('users without training page access cannot view crew training status', function () {
    $trainingDepartment = Department::create([
        'name' => 'Training',
        'code' => 'TR',
        'has_full_access' => false,
    ]);
    $otherDepartment = Department::create([
        'name' => 'Human Resources',
        'code' => 'HR',
        'has_full_access' => false,
    ]);

    Page::create([
        'department_id' => $trainingDepartment->id,
        'key' => 'training',
        'name' => 'Training',
        'route' => '/training',
        'is_active' => true,
    ]);

    Sanctum::actingAs(User::create([
        'name' => 'HR User',
        'email' => 'hr-user@example.test',
        'password' => 'password',
        'role' => User::ROLE_USER,
        'department_id' => $otherDepartment->id,
        'is_active' => true,
    ]));

    $this->getJson('/api/training')->assertForbidden();
});

<?php

use App\Models\Department;
use App\Models\Page;
use App\Models\User;
use App\Services\AccessService;
use Laravel\Sanctum\Sanctum;

test('dashboard shows only pages explicitly granted by Master alongside fixed shortcuts', function () {
    $department = Department::create([
        'name' => 'Operations',
        'code' => 'OPS',
        'has_full_access' => false,
    ]);
    $otherDepartment = Department::create([
        'name' => 'Finance',
        'code' => 'FIN',
        'has_full_access' => false,
    ]);
    $departmentPage = Page::create([
        'department_id' => $department->id,
        'key' => 'department-page',
        'name' => 'Department Page',
        'route' => '/department-page',
        'is_active' => true,
    ]);
    $sharedPage = Page::create([
        'department_id' => null,
        'key' => 'shared-page',
        'name' => 'Shared Page',
        'route' => '/shared-page',
        'is_active' => true,
    ]);
    $grantedPage = Page::create([
        'department_id' => $otherDepartment->id,
        'key' => 'granted-page',
        'name' => 'Granted Page',
        'route' => '/granted-page',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'department_id' => $department->id,
        'is_active' => true,
    ]);
    app(AccessService::class)->sync($user, [$sharedPage->id, $grantedPage->id]);
    Sanctum::actingAs($user);

    $this->getJson('/api/dashboard')
        ->assertOk()
        ->assertJsonCount(1, 'pages')
        ->assertJsonPath('pages.0.key', 'granted-page')
        ->assertJsonMissing(['key' => 'department-page'])
        ->assertJsonMissing(['key' => 'shared-page']);
});

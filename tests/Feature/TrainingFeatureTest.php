<?php

use App\Models\Crew;
use App\Models\CrewTraining;
use App\Models\Department;
use App\Models\Page;
use App\Models\TrainingType;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        'group' => 'fw_atr',
        'name' => 'Test Crew',
        'code' => 'TST',
        'category' => 'Pilot',
        'fleet' => 'Fixed Wing',
        'position' => 'PIC',
    ]);
    $type = TrainingType::create([
        'group' => 'fw_atr',
        'section' => 'Mandatory',
        'name' => 'Annual check',
        'validity' => '12 Months',
    ]);

    CrewTraining::create([
        'crew_id' => $crew->id,
        'training_type_id' => $type->id,
        'next_check' => now()->addDays(30)->toDateString(),
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/training')
        ->assertOk()
        ->assertJsonPath('summary.crew', 1)
        ->assertJsonPath('summary.soon', 1)
        ->assertJsonPath('crews.0.group_label', 'ATR');

    $this->getJson("/api/training/crews/{$crew->id}")
        ->assertOk()
        ->assertJsonPath('sections.0.items.0.name', 'Annual check')
        ->assertJsonPath('sections.0.items.0.status', 'soon')
        ->assertJsonPath('personal', null);
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

test('creating a crew creates a linked user and employee with the training category', function () {
    Page::create([
        'key' => 'training',
        'name' => 'Training',
        'route' => '/training',
        'is_active' => true,
    ]);
    Page::create([
        'key' => 'hr-employees',
        'name' => 'Employees',
        'route' => '/hr/employees',
        'is_active' => true,
    ]);

    Sanctum::actingAs(User::factory()->create([
        'role' => User::ROLE_MASTER,
        'is_active' => true,
    ]));

    foreach ([
        'fw_atr' => 'Pilot',
        'fa' => 'Flight Attendant',
        'foo_hlo' => 'FOO & HLO',
    ] as $group => $category) {
        $email = str_replace('_', '-', $group).'@example.test';
        $response = $this->postJson('/api/training/crews', [
            'group' => $group,
            ...($group === 'fw_atr' ? ['pilot_role' => 'PIC'] : []),
            'name' => "Crew {$group}",
            'email' => $email,
        ]);

        $response->assertCreated();

        $crew = Crew::with('user.employee')->findOrFail($response->json('id'));

        expect($crew->user->email)->toBe($email)
            ->and($crew->user->role)->toBe(User::ROLE_USER)
            ->and($crew->user->is_active)->toBeFalse()
            ->and($crew->user->employee->job_title)->toBe($category);

        $this->getJson("/api/training/crews/{$crew->id}")
            ->assertOk()
            ->assertJsonPath('personal.email', $email);

        $crew->user->update([
            'password' => 'CrewLogin123!',
            'is_active' => true,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $email,
            'password' => 'CrewLogin123!',
        ])->assertOk()->assertJsonPath('user.email', $email);
    }

    $this->getJson('/api/hr/employees')
        ->assertOk()
        ->assertJsonFragment(['job_title' => 'Pilot'])
        ->assertJsonFragment(['job_title' => 'Flight Attendant'])
        ->assertJsonFragment(['job_title' => 'FOO & HLO']);
});

test('adding an email to a legacy crew creates its user and employee account', function () {
    Page::create([
        'key' => 'training',
        'name' => 'Training',
        'route' => '/training',
        'is_active' => true,
    ]);

    $master = User::factory()->create([
        'role' => User::ROLE_MASTER,
        'is_active' => true,
    ]);
    $crew = Crew::create([
        'group' => 'fw_atr',
        'name' => 'Legacy Crew',
        'category' => 'Pilot',
        'fleet' => 'Fixed Wing',
        'position' => 'PIC',
    ]);

    Sanctum::actingAs($master);

    $this->putJson("/api/training/crews/{$crew->id}", [
        'name' => $crew->name,
        'email' => 'legacy-crew@example.test',
    ])->assertOk();

    $crew->refresh()->load('user.employee');

    expect($crew->user->email)->toBe('legacy-crew@example.test')
        ->and($crew->user->is_active)->toBeFalse()
        ->and($crew->user->employee->job_title)->toBe('Pilot');

    $this->getJson('/api/master/users')
        ->assertOk()
        ->assertJsonFragment(['email' => 'legacy-crew@example.test']);
});

test('master can assign view-only or editable access to a page', function () {
    $page = Page::create([
        'key' => 'training',
        'name' => 'Training',
        'route' => '/training',
        'is_active' => true,
    ]);
    $master = User::factory()->create([
        'role' => User::ROLE_MASTER,
        'is_active' => true,
    ]);
    $user = User::factory()->create(['is_active' => true]);

    Sanctum::actingAs($master);
    $this->putJson("/api/master/users/{$user->id}/access", [
        'page_ids' => [$page->id],
        'edit_page_ids' => [],
    ])->assertOk();

    $this->getJson("/api/master/users/{$user->id}/access")
        ->assertOk()
        ->assertJsonPath('pages.0.permission', 'view');

    Sanctum::actingAs($user);
    $this->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('pages.0.access_level', 'view');
    $this->getJson('/api/training')->assertOk();
    $this->postJson('/api/training/crews', [
        'group' => 'fw_atr',
        'pilot_role' => 'PIC',
        'name' => 'Viewer Crew',
        'email' => 'viewer-crew@example.test',
    ])->assertForbidden();

    Sanctum::actingAs($master);
    $this->putJson("/api/master/users/{$user->id}/access", [
        'page_ids' => [$page->id],
        'edit_page_ids' => [$page->id],
    ])->assertOk();
    $this->getJson("/api/master/users/{$user->id}/access")
        ->assertOk()
        ->assertJsonPath('pages.0.permission', 'edit');

    $user->refresh();
    Sanctum::actingAs($user);
    $this->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('pages.0.access_level', 'edit');
    $this->postJson('/api/training/crews', [
        'group' => 'fw_atr',
        'pilot_role' => 'PIC',
        'name' => 'Editor Crew',
        'email' => 'editor-crew@example.test',
    ])->assertCreated();
});

test('crew dashboard lists expired training in red alert data', function () {
    $user = User::factory()->create(['is_active' => true]);
    $crew = Crew::create([
        'user_id' => $user->id,
        'group' => 'fw_atr',
        'name' => $user->name,
        'category' => 'Pilot',
        'fleet' => 'Fixed Wing',
        'position' => 'PIC',
    ]);
    $expiredType = TrainingType::create([
        'group' => 'fw_atr',
        'section' => 'License',
        'name' => 'Pilot license',
        'validity' => '12 Months',
    ]);
    $validType = TrainingType::create([
        'group' => 'fw_atr',
        'section' => 'License',
        'name' => 'Medical check',
        'validity' => '12 Months',
    ]);
    CrewTraining::create([
        'crew_id' => $crew->id,
        'training_type_id' => $expiredType->id,
        'next_check' => now()->subDay()->toDateString(),
    ]);
    CrewTraining::create([
        'crew_id' => $crew->id,
        'training_type_id' => $validType->id,
        'next_check' => now()->addDays(30)->toDateString(),
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/dashboard')
        ->assertOk()
        ->assertJsonCount(1, 'expired_trainings')
        ->assertJsonPath('expired_trainings.0.name', 'Pilot license')
        ->assertJsonPath('expired_trainings.0.next_check', now()->subDay()->toDateString());
});

test('master can add an aircraft as a crew training group', function () {
    Page::create([
        'key' => 'training',
        'name' => 'Training',
        'route' => '/training',
        'is_active' => true,
    ]);

    Sanctum::actingAs(User::factory()->create([
        'role' => User::ROLE_MASTER,
        'is_active' => true,
    ]));

    $aircraft = $this->postJson('/api/training/aircraft', ['name' => 'Pilatus PC-12'])
        ->assertCreated()
        ->assertJsonPath('label', 'Pilatus PC-12')
        ->assertJsonPath('category', 'Pilot');
    $groupKey = $aircraft->json('key');

    $this->getJson('/api/training')
        ->assertOk()
        ->assertJsonFragment(['key' => $groupKey, 'label' => 'Pilatus PC-12', 'category' => 'Pilot']);

    $crewId = $this->postJson('/api/training/crews', [
        'group' => $groupKey,
        'pilot_role' => 'PIC',
        'name' => 'New Aircraft Crew',
        'email' => 'new-aircraft-crew@example.test',
    ])->assertCreated()->json('id');

    $crew = Crew::findOrFail($crewId);
    $atr = $this->getJson('/api/training')
        ->assertOk()
        ->json('aircrafts.0');
    $this->postJson("/api/training/crews/{$crewId}/aircrafts", [
        'aircraft_id' => $atr['id'],
    ])->assertCreated()->assertJsonCount(2, 'aircrafts');

    foreach ([
        ['group' => $groupKey, 'section' => 'PC-12 Checks', 'name' => 'PC-12 recurrent'],
        ['group' => 'fw_atr', 'section' => 'ATR Checks', 'name' => 'ATR recurrent'],
    ] as $typeData) {
        $type = TrainingType::create($typeData + ['validity' => '12 Months']);
        CrewTraining::create([
            'crew_id' => $crew->id,
            'training_type_id' => $type->id,
            'next_check' => now()->subDay()->toDateString(),
        ]);
    }

    $this->getJson("/api/training/crews/{$crewId}")
        ->assertOk()
        ->assertJsonPath('crew.group_label', 'Pilatus PC-12')
        ->assertJsonCount(2, 'crew.aircrafts')
        ->assertJsonCount(2, 'sections');
    $this->getJson('/api/training')
        ->assertOk()
        ->assertJsonPath('crews.0.expired', 2);

    $aircraftId = $aircraft->json('id');
    $this->deleteJson("/api/training/aircraft/{$aircraftId}")
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Pindahkan crew dari pesawat ini sebelum menghapusnya.');

    $this->deleteJson("/api/training/crews/{$crewId}/aircrafts/{$aircraftId}")->assertNoContent();
    $this->getJson("/api/training/crews/{$crewId}")
        ->assertOk()
        ->assertJsonPath('crew.group', 'fw_atr')
        ->assertJsonPath('crew.group_label', 'ATR')
        ->assertJsonCount(1, 'crew.aircrafts')
        ->assertJsonCount(1, 'sections');
    expect(Crew::findOrFail($crewId)->fleet)->toBe('ATR');

    $this->deleteJson("/api/training/crews/{$crewId}/aircrafts/{$atr['id']}")
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Crew harus memiliki minimal satu pesawat.');
    $this->deleteJson("/api/training/aircraft/{$aircraftId}")->assertNoContent();
    $this->getJson('/api/training')
        ->assertOk()
        ->assertJsonMissing(['key' => $groupKey, 'label' => 'Pilatus PC-12']);
});

test('license PDFs can be uploaded previewed by their crew and removed by an editor', function () {
    Storage::fake('local');
    Page::create([
        'key' => 'training',
        'name' => 'Training',
        'route' => '/training',
        'is_active' => true,
    ]);

    $crewUser = User::factory()->create(['is_active' => true]);
    $crew = Crew::create([
        'user_id' => $crewUser->id,
        'group' => 'fw_atr',
        'name' => $crewUser->name,
        'category' => 'Pilot',
        'fleet' => 'ATR',
        'position' => 'PIC',
    ]);
    $master = User::factory()->create([
        'role' => User::ROLE_MASTER,
        'is_active' => true,
    ]);

    Sanctum::actingAs($master);
    $response = $this->post("/api/training/crews/{$crew->id}/licenses", [
        'files' => [
            UploadedFile::fake()->create('pilot-license.pdf', 20, 'application/pdf'),
            UploadedFile::fake()->create('medical-license.pdf', 20, 'application/pdf'),
        ],
    ])->assertCreated()->assertJsonCount(2, 'documents');
    $documents = $crew->licenseDocuments()->get();

    expect($documents)->toHaveCount(2);
    foreach ($documents as $document) {
        Storage::disk('local')->assertExists($document->path);
    }

    $this->getJson("/api/training/crews/{$crew->id}")
        ->assertOk()
        ->assertJsonCount(2, 'license_documents');

    $document = $documents->first();
    $this->get("/api/training/crews/{$crew->id}/licenses/{$document->id}")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    Sanctum::actingAs($crewUser);
    $this->getJson("/api/training/crews/{$crew->id}")
        ->assertOk()
        ->assertJsonCount(2, 'license_documents');
    $this->get("/api/training/crews/{$crew->id}/licenses/{$document->id}")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    Sanctum::actingAs(User::factory()->create(['is_active' => true]));
    $this->get("/api/training/crews/{$crew->id}/licenses/{$document->id}")->assertForbidden();

    Sanctum::actingAs($master);
    $this->deleteJson("/api/training/crews/{$crew->id}/licenses/{$document->id}")->assertNoContent();
    Storage::disk('local')->assertMissing($document->path);
    expect($response->json('documents'))->toHaveCount(2);
});

test('PIC and SIC proficiency checks follow the crew role and can be changed by an editor', function () {
    $page = Page::create([
        'key' => 'training',
        'name' => 'Training',
        'route' => '/training',
        'is_active' => true,
    ]);
    $editor = User::factory()->create(['is_active' => true]);
    $editor->pages()->attach($page->id, ['access_level' => 'edit']);
    $viewer = User::factory()->create(['is_active' => true]);
    $viewer->pages()->attach($page->id, ['access_level' => 'view']);
    $crewUser = User::factory()->create(['is_active' => true]);
    $crew = Crew::create([
        'user_id' => $crewUser->id,
        'group' => 'fw_atr',
        'pilot_role' => 'PIC',
        'name' => 'Role Check Crew',
        'category' => 'Pilot',
        'fleet' => 'ATR',
        'position' => 'PIC',
    ]);

    foreach ([
        'Pilot Proficiency Check – Simulator PIC',
        'Pilot Proficiency Check – Simulator SIC',
        'Pilot Proficiency Check – Aircraft PIC',
        'Pilot Proficiency Check – Aircraft SIC',
    ] as $name) {
        $type = TrainingType::create([
            'group' => 'fw_atr',
            'section' => 'Proficiency Check',
            'name' => $name,
            'validity' => '12 Months',
        ]);
        CrewTraining::create([
            'crew_id' => $crew->id,
            'training_type_id' => $type->id,
            'next_check' => now()->subDay()->toDateString(),
        ]);
    }

    Sanctum::actingAs($editor);
    $this->getJson("/api/training/crews/{$crew->id}")
        ->assertOk()
        ->assertJsonPath('crew.pilot_role', 'PIC')
        ->assertJsonCount(2, 'sections.0.items')
        ->assertJsonMissing(['name' => 'Pilot Proficiency Check – Simulator SIC'])
        ->assertJsonMissing(['name' => 'Pilot Proficiency Check – Aircraft SIC']);
    $this->getJson('/api/training')
        ->assertOk()
        ->assertJsonPath('crews.0.expired', 2);
    $sicType = TrainingType::query()
        ->where('name', 'Pilot Proficiency Check – Simulator SIC')
        ->firstOrFail();
    $this->putJson("/api/training/crews/{$crew->id}/records/{$sicType->id}", [
        'next_check' => now()->addYear()->toDateString(),
    ])->assertNotFound();

    $this->putJson("/api/training/crews/{$crew->id}", [
        'name' => $crew->name,
        'pilot_role' => 'SIC',
    ])->assertOk();
    $this->getJson("/api/training/crews/{$crew->id}")
        ->assertOk()
        ->assertJsonPath('crew.pilot_role', 'SIC')
        ->assertJsonCount(2, 'sections.0.items')
        ->assertJsonMissing(['name' => 'Pilot Proficiency Check – Simulator PIC'])
        ->assertJsonMissing(['name' => 'Pilot Proficiency Check – Aircraft PIC']);

    Sanctum::actingAs($viewer);
    $this->putJson("/api/training/crews/{$crew->id}", [
        'name' => $crew->name,
        'pilot_role' => 'PIC',
    ])->assertForbidden();

    Sanctum::actingAs($crewUser);
    $this->getJson('/api/dashboard')
        ->assertOk()
        ->assertJsonCount(2, 'expired_trainings')
        ->assertJsonMissing(['name' => 'Pilot Proficiency Check – Simulator PIC'])
        ->assertJsonMissing(['name' => 'Pilot Proficiency Check – Aircraft PIC']);

    Sanctum::actingAs(User::factory()->create([
        'role' => User::ROLE_MASTER,
        'is_active' => true,
    ]));
    $this->putJson("/api/training/crews/{$crew->id}", [
        'name' => $crew->name,
        'pilot_role' => 'PIC',
    ])->assertOk();
    $this->getJson("/api/training/crews/{$crew->id}")
        ->assertOk()
        ->assertJsonPath('crew.pilot_role', 'PIC')
        ->assertJsonMissing(['name' => 'Pilot Proficiency Check – Simulator SIC']);
});

<?php

use App\Models\Crew;
use App\Models\CrewTraining;
use App\Models\Page;
use App\Models\TrainingProgram;
use App\Models\TrainingType;
use App\Models\User;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;

function createTrainingProgramPage(): Page
{
    return Page::create([
        'key' => 'training',
        'name' => 'Training',
        'route' => '/training',
        'is_active' => true,
    ]);
}

test('training managers can view plans, generate them and save actual training', function () {
    createTrainingProgramPage();
    $manager = User::factory()->create([
        'role' => User::ROLE_MASTER,
        'is_active' => true,
    ]);
    Sanctum::actingAs($manager);

    $crewUser = User::factory()->create(['is_active' => true]);
    $crew = Crew::create([
        'user_id' => $crewUser->id,
        'group' => 'fa',
        'name' => $crewUser->name,
        'category' => 'Flight Attendant',
        'fleet' => '',
        'position' => 'FA',
    ]);
    $type = TrainingType::create([
        'group' => 'fa',
        'section' => 'Mandatory',
        'name' => 'Recurrent Training',
        'validity' => '12 Months',
    ]);
    $lastCheck = now()->subDays(30)->toDateString();
    CrewTraining::create([
        'crew_id' => $crew->id,
        'training_type_id' => $type->id,
        'last_check' => $lastCheck,
    ]);
    $program = TrainingProgram::create([
        'category' => 'fa',
        'name' => 'Recurrent FA',
    ]);
    $program->types()->attach($type->id);

    $year = now()->year + 1;
    $dueDate = Carbon::parse($lastCheck)->addYearNoOverflow();

    $this->getJson("/api/training/program?year=$year")
        ->assertOk()
        ->assertJsonPath('can.manage', true)
        ->assertJsonPath('programs.0.name', 'Recurrent FA');

    $this->postJson('/api/training/program/plan/generate', ['year' => $year])
        ->assertOk()
        ->assertJsonPath('created', 1);

    $this->getJson("/api/training/program?year=$year")
        ->assertOk()
        ->assertJsonPath('programs.0.plans.0.month', $dueDate->month)
        ->assertJsonPath('programs.0.plans.0.due_date', $dueDate->toDateString());

    $heldOn = Carbon::create($year, $dueDate->month, 10)->toDateString();
    $this->putJson(
        "/api/training/program/{$program->id}/actuals/{$year}/{$dueDate->month}",
        [
            'held_on' => $heldOn,
            'crew_ids' => [$crew->id],
            'update_records' => true,
        ],
    )->assertOk()->assertJsonPath('message', 'Realisasi tersimpan. Last/next check diperbarui untuk 1 crew.');

    expect($crew->trainings()->first()->fresh()->last_check->toDateString())
        ->toBe($heldOn)
        ->and($crew->trainings()->first()->fresh()->next_check->toDateString())
        ->toBe(Carbon::parse($heldOn)->addYearNoOverflow()->toDateString());
});

test('training viewers can read programs but cannot change them', function () {
    $page = createTrainingProgramPage();
    $viewer = User::factory()->create(['is_active' => true]);
    $viewer->pages()->attach($page->id, ['access_level' => 'view']);
    Sanctum::actingAs($viewer);

    $this->getJson('/api/training/program')
        ->assertOk()
        ->assertJsonPath('can.manage', false);

    $this->postJson('/api/training/program', [
        'category' => 'mandatory',
        'name' => 'CRM',
    ])->assertForbidden();

    expect(TrainingProgram::query()->count())->toBe(0);
});

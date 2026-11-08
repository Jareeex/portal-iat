<?php

use App\Models\Department;
use App\Models\User;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Hr\Models\LeaveType;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

function assignHrLeaveManager(User $requester): User
{
    $department = Department::create([
        'name' => fake()->unique()->company(),
        'code' => fake()->unique()->bothify('D##'),
        'has_full_access' => false,
    ]);

    $requester->update(['department_id' => $department->id]);
    $manager = User::factory()->create([
        'department_id' => $department->id,
        'is_active' => true,
    ]);
    $manager->employee()->update(['job_title' => 'Manager']);

    return $manager;
}

function hrLeaveRequestPayload(LeaveType $type, User $manager): array
{
    $startDate = now()->addWeek()->startOfWeek()->toDateString();

    return [
        'leave_type_id' => $type->id,
        'manager_id' => $manager->id,
        'start_date' => $startDate,
        'end_date' => $startDate,
        'reason' => 'Keperluan keluarga',
    ];
}

test('all users can submit leave requests only to an active manager in their department', function () {
    $user = User::factory()->create(['is_active' => true]);
    $manager = assignHrLeaveManager($user);
    $otherDepartmentManager = User::factory()->create(['is_active' => true]);
    $otherDepartmentManager->employee()->update(['job_title' => 'Manager']);
    $leaveType = LeaveType::where('code', 'ANNUAL')->firstOrFail();

    Sanctum::actingAs($user);

    $this->getJson('/api/hr/leave-managers')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $manager->id);

    $this->postJson('/api/hr/leave-requests', [
        ...hrLeaveRequestPayload($leaveType, $manager),
        'manager_id' => $otherDepartmentManager->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('manager_id');

    $response = $this->postJson('/api/hr/leave-requests', [
        ...hrLeaveRequestPayload($leaveType, $manager),
        'employee_id' => 999,
        'days' => 300,
        'status' => LeaveRequest::APPROVED,
        'review_note' => 'Injected note',
    ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Pengajuan cuti berhasil dikirim ke manager.')
        ->assertJsonMissingPath('data');

    $leave = LeaveRequest::firstOrFail();
    expect($leave->employee_id)->toBe($user->employee->id)
        ->and($leave->manager_id)->toBe($manager->id)
        ->and($leave->days)->toBe(1)
        ->and($leave->status)->toBe(LeaveRequest::MANAGER_PENDING)
        ->and($leave->review_note)->toBeNull();

    $this->getJson('/api/hr/leave-requests')->assertForbidden();
    $this->getJson('/api/hr/leave-requests/inbox')->assertForbidden();
    $this->getJson("/api/hr/leave-requests/{$leave->id}/attachment")->assertForbidden();
    $this->putJson("/api/hr/leave-requests/{$leave->id}", hrLeaveRequestPayload($leaveType, $manager))
        ->assertForbidden();

    Sanctum::actingAs($otherDepartmentManager);
    $this->getJson('/api/hr/leave-requests/manager-inbox')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('the selected manager approves first and only then the request enters HR inbox', function () {
    $employee = User::factory()->create(['is_active' => true]);
    $manager = assignHrLeaveManager($employee);
    $hr = User::factory()->create([
        'role' => User::ROLE_MASTER,
        'is_active' => true,
    ]);
    $leaveType = LeaveType::where('code', 'ANNUAL')->firstOrFail();

    Sanctum::actingAs($employee);
    $this->postJson('/api/hr/leave-requests', hrLeaveRequestPayload($leaveType, $manager))
        ->assertCreated();

    $leave = LeaveRequest::firstOrFail();
    Sanctum::actingAs($hr);
    $this->getJson('/api/hr/leave-requests/inbox')
        ->assertOk()
        ->assertJsonCount(0, 'data');
    $this->postJson("/api/hr/leave-requests/{$leave->id}/approve")
        ->assertForbidden();

    Sanctum::actingAs($manager);
    $this->getJson('/api/hr/leave-requests/manager-inbox')
        ->assertOk()
        ->assertJsonPath('data.0.id', $leave->id)
        ->assertJsonPath('data.0.employee.name', $employee->name);

    $disk = config('hr.leave.attachment_disk');
    Storage::fake($disk);
    Storage::disk($disk)->put('hr/leave-attachments/request.pdf', 'attachment');
    $leave->forceFill(['attachment_path' => 'hr/leave-attachments/request.pdf'])->save();
    $this->getJson("/api/hr/leave-requests/{$leave->id}/attachment")->assertOk();

    $this->postJson("/api/hr/leave-requests/{$leave->id}/manager-approve", [
        'review_note' => 'Disetujui manager.',
    ])->assertOk()
        ->assertJsonPath('data.status', LeaveRequest::HR_PENDING)
        ->assertJsonPath('data.manager_review_note', 'Disetujui manager.');

    expect($leave->fresh()->manager_reviewed_by)->toBe($manager->id);
    $this->getJson("/api/hr/leave-requests/{$leave->id}/attachment")->assertForbidden();

    Sanctum::actingAs($hr);
    $this->getJson('/api/hr/leave-requests/inbox')
        ->assertOk()
        ->assertJsonPath('data.0.id', $leave->id);

    $updatedDate = now()->addWeeks(2)->startOfWeek()->toDateString();
    $this->putJson("/api/hr/leave-requests/{$leave->id}", [
        ...hrLeaveRequestPayload($leaveType, $manager),
        'start_date' => $updatedDate,
        'end_date' => $updatedDate,
        'reason' => 'Diperbarui oleh HR',
    ])->assertOk()
        ->assertJsonPath('data.reason', 'Diperbarui oleh HR');

    expect($leave->fresh()->status)->toBe(LeaveRequest::HR_PENDING)
        ->and($leave->fresh()->start_date->toDateString())->toBe($updatedDate);

    $this->postJson("/api/hr/leave-requests/{$leave->id}/approve", [
        'review_note' => 'Keputusan akhir HR.',
    ])->assertOk()
        ->assertJsonPath('data.status', LeaveRequest::APPROVED)
        ->assertJsonPath('data.review_note', 'Keputusan akhir HR.');

    expect($leave->fresh()->reviewed_by)->toBe($hr->id);
});

test('manager rejection requires a note and rejected requests do not reach HR', function () {
    $employee = User::factory()->create(['is_active' => true]);
    $manager = assignHrLeaveManager($employee);
    $leaveType = LeaveType::where('code', 'ANNUAL')->firstOrFail();

    Sanctum::actingAs($employee);
    $this->postJson('/api/hr/leave-requests', hrLeaveRequestPayload($leaveType, $manager))
        ->assertCreated();
    $leave = LeaveRequest::firstOrFail();

    Sanctum::actingAs($manager);
    $this->postJson("/api/hr/leave-requests/{$leave->id}/manager-reject")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('review_note');

    $this->postJson("/api/hr/leave-requests/{$leave->id}/manager-reject", [
        'review_note' => 'Perlu penyesuaian jadwal.',
    ])->assertOk()
        ->assertJsonPath('data.status', LeaveRequest::MANAGER_REJECTED);

    expect($leave->fresh()->manager_reviewed_by)->toBe($manager->id);

    $hr = User::factory()->create([
        'role' => User::ROLE_MASTER,
        'is_active' => true,
    ]);
    Sanctum::actingAs($hr);
    $this->getJson('/api/hr/leave-requests/inbox?status=all')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('HR can manage job positions and employee job titles must use an active position', function () {
    $hr = User::factory()->create([
        'role' => User::ROLE_MASTER,
        'is_active' => true,
    ]);
    $employee = User::factory()->create(['is_active' => true]);
    Sanctum::actingAs($hr);

    $positions = $this->getJson('/api/hr/job-positions')
        ->assertOk()
        ->assertJsonPath('0.name', 'OJT')
        ->assertJsonPath('4.name', 'Manager')
        ->assertJsonPath('4.is_leave_manager', true);

    $position = $this->postJson('/api/hr/job-positions', [
        'name' => 'Director',
        'sort_order' => 7,
        'is_active' => true,
        'is_leave_manager' => false,
    ])->assertCreated()
        ->assertJsonPath('name', 'Director')
        ->json();

    $this->putJson("/api/hr/employees/{$employee->employee->id}", [
        'job_title' => 'Director',
    ])->assertOk()
        ->assertJsonPath('data.job_title', 'Director');

    $this->putJson("/api/hr/job-positions/{$position['id']}", [
        'name' => 'Executive',
        'sort_order' => 7,
        'is_active' => true,
        'is_leave_manager' => false,
    ])->assertOk();

    expect($employee->employee->fresh()->job_title)->toBe('Executive');
});

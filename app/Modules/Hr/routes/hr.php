<?php

use App\Modules\Hr\Http\Controllers\AttendanceController;
use App\Modules\Hr\Http\Controllers\EmployeeController;
use App\Modules\Hr\Http\Controllers\ExperienceController;
use App\Modules\Hr\Http\Controllers\JobPositionController;
use App\Modules\Hr\Http\Controllers\LeaveRequestController;
use App\Modules\Hr\Http\Controllers\LeaveReviewController;
use App\Modules\Hr\Http\Controllers\MyProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware(config('hr.route.middleware'))
    ->prefix(config('hr.route.prefix'))
    ->name(config('hr.route.name'))
    ->group(function () {
        Route::get('attendance', [AttendanceController::class, 'index'])
            ->name('attendance.index');
        Route::post('attendance', [AttendanceController::class, 'storeManual'])
            ->middleware('page.access:hr-employees,edit')
            ->name('attendance.store');
        Route::put('attendance/settings', [AttendanceController::class, 'updateSettings'])
            ->middleware('page.access:hr-employees,edit')
            ->name('attendance.settings.update');
        Route::post('attendance/clock-in', [AttendanceController::class, 'clockIn'])
            ->name('attendance.clock-in');
        Route::post('attendance/clock-out', [AttendanceController::class, 'clockOut'])
            ->name('attendance.clock-out');
        Route::get('attendance/{attendance}/photo/{type}', [AttendanceController::class, 'photo'])
            ->whereIn('type', ['clock-in', 'clock-out'])
            ->name('attendance.photo');
        Route::put('attendance/{attendance}', [AttendanceController::class, 'update'])
            ->middleware('page.access:hr-employees,edit')
            ->name('attendance.update');
        Route::delete('attendance/{attendance}', [AttendanceController::class, 'destroy'])
            ->middleware('page.access:hr-employees,edit')
            ->name('attendance.destroy');

        Route::get('employees', [EmployeeController::class, 'index'])
            ->middleware('page.access:hr-employees')
            ->name('employees.index');
        Route::get('employees/unlinked', [EmployeeController::class, 'unlinked'])
            ->middleware('role.master')
            ->name('employees.unlinked');
        Route::post('employees', [EmployeeController::class, 'store'])
            ->middleware('page.access:hr-employees,edit')
            ->name('employees.store');
        Route::get('employees/{employee}', [EmployeeController::class, 'show'])
            ->name('employees.show');
        Route::put('employees/{employee}', [EmployeeController::class, 'update'])
            ->middleware('page.access:hr-employees,edit')
            ->name('employees.update');

        Route::get('me', [MyProfileController::class, 'show'])->name('me.show');
        Route::put('me', [MyProfileController::class, 'update'])->name('me.update');

        Route::get('job-positions', [JobPositionController::class, 'index'])->name('job-positions.index');
        Route::post('job-positions', [JobPositionController::class, 'store'])->name('job-positions.store');
        Route::put('job-positions/{jobPosition}', [JobPositionController::class, 'update'])->name('job-positions.update');

        // All authenticated users can submit; only HR can read and review requests.
        Route::get('leave-types', [LeaveRequestController::class, 'types'])
            ->name('leave-types.index');
        Route::get('leave-managers', [LeaveRequestController::class, 'managers'])
            ->name('leave-managers.index');
        Route::post('leave-requests', [LeaveRequestController::class, 'store'])
            ->name('leave-requests.store');
        Route::get('leave-requests/manager-inbox', [LeaveReviewController::class, 'managerInbox'])
            ->name('leave-requests.manager-inbox');
        Route::post('leave-requests/{leaveRequest}/manager-approve', [LeaveReviewController::class, 'managerApprove'])
            ->name('leave-requests.manager-approve');
        Route::post('leave-requests/{leaveRequest}/manager-reject', [LeaveReviewController::class, 'managerReject'])
            ->name('leave-requests.manager-reject');
        Route::get('leave-requests', [LeaveReviewController::class, 'index'])
            ->name('leave-requests.summary');
        Route::get('leave-requests/inbox', [LeaveRequestController::class, 'index'])
            ->name('leave-requests.index');
        Route::put('leave-requests/{leaveRequest}', [LeaveRequestController::class, 'update'])
            ->name('leave-requests.update');
        Route::get('leave-requests/{leaveRequest}/attachment', [LeaveRequestController::class, 'attachment'])
            ->name('leave-requests.attachment');
        Route::post('leave-requests/{leaveRequest}/approve', [LeaveReviewController::class, 'approve'])
            ->name('leave-requests.approve');
        Route::post('leave-requests/{leaveRequest}/reject', [LeaveReviewController::class, 'reject'])
            ->name('leave-requests.reject');

        Route::post('employees/{employee}/experiences', [ExperienceController::class, 'store'])
            ->name('employees.experiences.store');
        Route::put('employees/{employee}/experiences/{experience}', [ExperienceController::class, 'update'])
            ->name('employees.experiences.update');
        Route::delete('employees/{employee}/experiences/{experience}', [ExperienceController::class, 'destroy'])
            ->name('employees.experiences.destroy');
    });

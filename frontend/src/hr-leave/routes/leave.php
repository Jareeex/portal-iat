<?php

use App\Modules\Hr\Http\Controllers\LeaveRequestController;
use App\Modules\Hr\Http\Controllers\LeaveReviewController;
use App\Modules\Hr\Http\Controllers\LeaveTypeController;
use Illuminate\Support\Facades\Route;

Route::middleware(config('hr.route.middleware'))
    ->prefix(config('hr.route.prefix'))
    ->name(config('hr.route.name'))
    ->group(function () {

        // ----- Karyawan: input & pantau pengajuan sendiri -----
        Route::get('leaves', [LeaveRequestController::class, 'index'])->name('leaves.index');
        Route::get('leaves/create', [LeaveRequestController::class, 'create'])->name('leaves.create');
        Route::post('leaves', [LeaveRequestController::class, 'store'])->name('leaves.store');
        Route::get('leaves/{leaveRequest}', [LeaveRequestController::class, 'show'])->name('leaves.show');
        Route::post('leaves/{leaveRequest}/cancel', [LeaveRequestController::class, 'cancel'])->name('leaves.cancel');
        Route::get('leaves/{leaveRequest}/attachment', [LeaveRequestController::class, 'attachment'])->name('leaves.attachment');

        // ----- HR: inbox + keputusan -----
        Route::get('leave-requests', [LeaveReviewController::class, 'index'])->name('leave-requests.index');
        Route::post('leaves/{leaveRequest}/approve', [LeaveReviewController::class, 'approve'])->name('leaves.approve');
        Route::post('leaves/{leaveRequest}/reject', [LeaveReviewController::class, 'reject'])->name('leaves.reject');

        // ----- HR: master jenis cuti -----
        Route::get('leave-types', [LeaveTypeController::class, 'index'])->name('leave-types.index');
        Route::post('leave-types', [LeaveTypeController::class, 'store'])->name('leave-types.store');
        Route::put('leave-types/{leaveType}', [LeaveTypeController::class, 'update'])->name('leave-types.update');
        Route::delete('leave-types/{leaveType}', [LeaveTypeController::class, 'destroy'])->name('leave-types.destroy');
    });

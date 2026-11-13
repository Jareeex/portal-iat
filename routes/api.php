<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Master\AccessController;
use App\Http\Controllers\Master\DepartmentController;
use App\Http\Controllers\Master\PageController;
use App\Http\Controllers\Master\TrainingReminderController;
use App\Http\Controllers\Master\UserController;
use App\Http\Controllers\User\DashboardController;
use App\Http\Controllers\User\NotificationController;
use App\Http\Controllers\User\ReportController;
use App\Http\Controllers\User\TrainingController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/training-program.php';

// ---------- Auth (SSO: login sekali, token dipakai untuk semua fitur portal) ----------
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::put('password', [AuthController::class, 'updatePassword']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

Route::middleware('auth:sanctum')->group(function () {

    // ---------- Master: kelola user, halaman/fitur, dan hak akses ----------
    Route::prefix('master')->middleware('role.master')->group(function () {
        Route::apiResource('departments', DepartmentController::class);
        Route::apiResource('users', UserController::class);
        Route::apiResource('pages', PageController::class);
        Route::get('training-reminders/recipients', [TrainingReminderController::class, 'index']);
        Route::put('training-reminders/recipients/{user}', [TrainingReminderController::class, 'updateRecipient']);
        Route::post('training-reminders/send-all', [TrainingReminderController::class, 'sendAll']);

        Route::get('users/{user}/access', [AccessController::class, 'show']);
        Route::put('users/{user}/access', [AccessController::class, 'update']);
    });

    // ---------- User: dashboard + fitur yang di-assign master ----------
    Route::get('dashboard', [DashboardController::class, 'index']);
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])
        ->whereUuid('notification');

    Route::get('reports', [ReportController::class, 'index'])->middleware('page.access:reports');
    Route::prefix('training')->middleware('page.access:training')->group(function () {
        Route::get('/', [TrainingController::class, 'index']);
        Route::get('export', [TrainingController::class, 'export'])->middleware('page.access:training,edit');
        Route::post('aircraft', [TrainingController::class, 'storeAircraft'])->middleware('page.access:training,edit');
        Route::delete('aircraft/{aircraft}', [TrainingController::class, 'destroyAircraft'])
            ->middleware('page.access:training,edit');
        Route::post('crews/{crew}/aircrafts', [TrainingController::class, 'storeCrewAircraft'])
            ->middleware('page.access:training,edit');
        Route::delete('crews/{crew}/aircrafts/{aircraft}', [TrainingController::class, 'destroyCrewAircraft'])
            ->middleware('page.access:training,edit');
        Route::get('crews/{crew}', [TrainingController::class, 'show']);
        Route::post('crews/{crew}/licenses', [TrainingController::class, 'storeLicenseDocuments'])
            ->middleware('page.access:training,edit');
        Route::get('crews/{crew}/licenses/{document}', [TrainingController::class, 'showLicenseDocument']);
        Route::delete('crews/{crew}/licenses/{document}', [TrainingController::class, 'destroyLicenseDocument'])
            ->middleware('page.access:training,edit');

        Route::post('crews', [TrainingController::class, 'store'])->middleware('page.access:training,edit');
        Route::put('crews/{crew}', [TrainingController::class, 'update'])->middleware('page.access:training,edit');
        Route::delete('crews/{crew}', [TrainingController::class, 'destroy'])->middleware('page.access:training,edit');
        Route::put('crews/{crew}/records/{type}', [TrainingController::class, 'saveRecord'])
            ->middleware('page.access:training,edit');
    });
    // Fitur baru tinggal tambah: ->middleware('page.access:<key halaman>')
});

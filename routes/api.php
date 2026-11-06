<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Master\AccessController;
use App\Http\Controllers\Master\DepartmentController;
use App\Http\Controllers\Master\PageController;
use App\Http\Controllers\Master\UserController;
use App\Http\Controllers\User\DashboardController;
use App\Http\Controllers\User\ReportController;
use App\Http\Controllers\User\TrainingController;
use Illuminate\Support\Facades\Route;

// ---------- Auth (SSO: login sekali, token dipakai untuk semua fitur portal) ----------
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

Route::middleware('auth:sanctum')->group(function () {

    // ---------- Master: kelola user, halaman/fitur, dan hak akses ----------
    Route::prefix('master')->middleware('role.master')->group(function () {
        Route::apiResource('departments', DepartmentController::class);
        Route::apiResource('users', UserController::class);
        Route::apiResource('pages', PageController::class);

        Route::get('users/{user}/access', [AccessController::class, 'show']);
        Route::put('users/{user}/access', [AccessController::class, 'update']);
    });

    // ---------- User: dashboard + fitur yang di-assign master ----------
    Route::get('dashboard', [DashboardController::class, 'index']);

    Route::get('reports', [ReportController::class, 'index'])->middleware('page.access:reports');
    Route::get('training', [TrainingController::class, 'index'])->middleware('page.access:training');
    // Fitur baru tinggal tambah: ->middleware('page.access:<key halaman>')
});

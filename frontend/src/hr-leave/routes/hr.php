<?php

use App\Modules\Hr\Http\Controllers\EmployeeController;
use App\Modules\Hr\Http\Controllers\ExperienceController;
use App\Modules\Hr\Http\Controllers\MyProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware(config('hr.route.middleware'))
    ->prefix(config('hr.route.prefix'))
    ->name(config('hr.route.name'))
    ->group(function () {

        // Self-service (karyawan = user yang login)
        Route::get('me', [MyProfileController::class, 'show'])->name('me.show');
        Route::get('me/edit', [MyProfileController::class, 'edit'])->name('me.edit');
        Route::put('me', [MyProfileController::class, 'update'])->name('me.update');

        // HR / admin
        Route::resource('employees', EmployeeController::class);

        // Pengalaman kerja (outside / inside)
        Route::post('employees/{employee}/experiences', [ExperienceController::class, 'store'])
            ->name('employees.experiences.store');
        Route::put('employees/{employee}/experiences/{experience}', [ExperienceController::class, 'update'])
            ->name('employees.experiences.update');
        Route::delete('employees/{employee}/experiences/{experience}', [ExperienceController::class, 'destroy'])
            ->name('employees.experiences.destroy');
    });

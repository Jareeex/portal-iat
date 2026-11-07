<?php

use App\Modules\Hr\Http\Controllers\EmployeeController;
use App\Modules\Hr\Http\Controllers\ExperienceController;
use App\Modules\Hr\Http\Controllers\MyProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware(config('hr.route.middleware'))
    ->prefix(config('hr.route.prefix'))
    ->name(config('hr.route.name'))
    ->group(function () {
        Route::get('employees', [EmployeeController::class, 'index'])
            ->middleware('page.access:hr-employees')
            ->name('employees.index');
        Route::get('employees/{employee}', [EmployeeController::class, 'show'])
            ->name('employees.show');
        Route::put('employees/{employee}', [EmployeeController::class, 'update'])
            ->middleware('page.access:hr-employees,edit')
            ->name('employees.update');

        Route::get('me', [MyProfileController::class, 'show'])->name('me.show');
        Route::put('me', [MyProfileController::class, 'update'])->name('me.update');

        Route::post('employees/{employee}/experiences', [ExperienceController::class, 'store'])
            ->name('employees.experiences.store');
        Route::put('employees/{employee}/experiences/{experience}', [ExperienceController::class, 'update'])
            ->name('employees.experiences.update');
        Route::delete('employees/{employee}/experiences/{experience}', [ExperienceController::class, 'destroy'])
            ->name('employees.experiences.destroy');
    });

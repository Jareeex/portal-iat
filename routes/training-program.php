<?php

use App\Http\Controllers\User\TrainingProgramController as P;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'page.access:training'])->prefix('training/program')->name('training.program.')->group(function () {
    Route::get('/', [P::class, 'index'])->name('index');
    Route::post('plan/generate', [P::class, 'generatePlan'])->name('plan.generate');
    Route::put('plans/{plan}', [P::class, 'updatePlan'])->name('plans.update');
    Route::delete('plans/{plan}', [P::class, 'destroyPlan'])->name('plans.destroy');
    Route::delete('actuals/{actual}', [P::class, 'destroyActual'])->name('actuals.destroy');

    Route::post('/', [P::class, 'storeProgram'])->name('store');
    Route::put('{program}', [P::class, 'updateProgram'])->name('update');
    Route::delete('{program}', [P::class, 'destroyProgram'])->name('destroy');
    Route::post('{program}/plans', [P::class, 'storePlan'])->name('plans.store');
    Route::put('{program}/actuals/{year}/{month}', [P::class, 'saveActual'])->name('actuals.save');
});

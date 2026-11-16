<?php

use App\Modules\Fis\Http\Controllers\AirportController;
use App\Modules\Fis\Http\Controllers\FlightController;
use Illuminate\Support\Facades\Route;

Route::middleware(config('fis.route.middleware'))
    ->prefix(config('fis.route.prefix'))
    ->name(config('fis.route.name'))
    ->group(function () {
        Route::middleware('page.access:fis')->group(function () {
            Route::get('airports', [AirportController::class, 'index'])->name('airports.index');
            Route::get('aircraft', [FlightController::class, 'aircraft'])->name('aircraft.index');
            Route::get('flights', [FlightController::class, 'index'])->name('flights.index');
            Route::get('flights/{flight}/report', [FlightController::class, 'report'])
                ->whereNumber('flight')
                ->name('flights.report');
        });

        Route::middleware('page.access:fis,edit')->group(function () {
            Route::post('flights', [FlightController::class, 'store'])->name('flights.store');
            Route::put('flights/{flight}', [FlightController::class, 'update'])
                ->whereNumber('flight')
                ->name('flights.update');
            Route::delete('flights/{flight}', [FlightController::class, 'destroy'])
                ->whereNumber('flight')
                ->name('flights.destroy');
        });
    });

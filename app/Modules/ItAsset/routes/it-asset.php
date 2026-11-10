<?php

use App\Modules\ItAsset\Http\Controllers\AssetCategoryController;
use App\Modules\ItAsset\Http\Controllers\AssetController;
use App\Modules\ItAsset\Http\Controllers\AssetPdfController;
use Illuminate\Support\Facades\Route;

Route::middleware(config('it-asset.route.middleware'))
    ->prefix(config('it-asset.route.prefix'))
    ->name(config('it-asset.route.name'))
    ->group(function () {
        Route::middleware('page.access:it-assets')->group(function () {
            Route::get('', [AssetController::class, 'index'])->name('index');
            Route::get('mine', [AssetController::class, 'mine'])->name('mine');
            Route::get('summary', [AssetController::class, 'summary'])->name('summary');
            Route::get('categories', [AssetCategoryController::class, 'index'])->name('categories.index');
            Route::get('export', [AssetPdfController::class, 'export'])->name('export');
        });

        Route::middleware('page.access:it-assets,edit')->group(function () {
            Route::post('categories', [AssetCategoryController::class, 'store'])->name('categories.store');
            Route::put('categories/{category}', [AssetCategoryController::class, 'update'])->name('categories.update');
            Route::delete('categories/{category}', [AssetCategoryController::class, 'destroy'])->name('categories.destroy');

            Route::post('', [AssetController::class, 'store'])->name('store');
            Route::put('{asset}', [AssetController::class, 'update'])->name('update')->whereNumber('asset');
            Route::delete('{asset}', [AssetController::class, 'destroy'])->name('destroy')->whereNumber('asset');
        });
    });

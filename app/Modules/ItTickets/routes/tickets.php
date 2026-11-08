<?php

use App\Modules\ItTickets\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'auth:sanctum'])
    ->prefix('api/it-tickets')
    ->group(function (): void {
        Route::get('/', [TicketController::class, 'index']);
        Route::get('alerts', [TicketController::class, 'alerts']);
        Route::get('options', [TicketController::class, 'options']);
        Route::post('/', [TicketController::class, 'store']);
        Route::get('attachments/{attachment}', [TicketController::class, 'attachment']);
        Route::get('{ticket}', [TicketController::class, 'show'])->whereNumber('ticket');
        Route::put('{ticket}', [TicketController::class, 'update'])->whereNumber('ticket');
        Route::post('{ticket}/take', [TicketController::class, 'take'])->whereNumber('ticket');
        Route::post('{ticket}/comments', [TicketController::class, 'comment'])->whereNumber('ticket');
        Route::post('{ticket}/close', [TicketController::class, 'close'])->whereNumber('ticket');
        Route::post('{ticket}/reopen', [TicketController::class, 'reopen'])->whereNumber('ticket');
    });

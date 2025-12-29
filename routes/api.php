<?php

use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Middleware\IdempotencyMiddleware;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Route::post('/events/{eventId}/reserve-unsafe', [TicketController::class, 'reserveUnsafe']);
    // Route::post('/events/{eventId}/reserve', [TicketController::class, 'reserveSecure']);

    Route::post('/login', [LoginController::class, 'authenticate']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/events/{eventId}/reserve-pending', [TicketController::class, 'reservePending'])
            ->middleware(IdempotencyMiddleware::class);
        Route::post('/reservations/{reservationId}/confirm', [TicketController::class, 'confirmReservation'])
            ->middleware(IdempotencyMiddleware::class);

        Route::post('/reservations/{reservationId}/cancel', [TicketController::class, 'cancelReservation']);

        Route::post('/logout', [LoginController::class, 'logout']);
    });
});

<?php

use App\Http\Controllers\Api\TicketController;
use App\Http\Middleware\IdempotencyMiddleware;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/events/{eventId}/reserve-unsafe', [TicketController::class, 'reserveUnsafe']);
    Route::post('/events/{eventId}/reserve', [TicketController::class, 'reserveSecure']);


    // 仮予約エンドポイント
    Route::post('/events/{eventId}/reserve-pending', [TicketController::class, 'reservePending'])
        ->middleware(IdempotencyMiddleware::class);
    // 予約確定エンドポイント (ミドルウェア適用)
    Route::post('/reservations/{reservationId}/confirm', [TicketController::class, 'confirmReservation'])
        ->middleware(IdempotencyMiddleware::class);
});

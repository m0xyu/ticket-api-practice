<?php

use App\Http\Controllers\Api\TicketController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // 脆弱なエンドポイント (実験用)
    Route::post('/events/{eventId}/reserve-unsafe', [TicketController::class, 'reserveUnsafe']);
    // 堅牢なエンドポイント (本番用)
    Route::post('/events/{eventId}/reserve', [TicketController::class, 'reserveSecure']);
});

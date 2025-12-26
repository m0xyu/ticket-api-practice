<?php

use App\Http\Controllers\Api\TicketController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/events/{eventId}/reserve', [TicketController::class, 'reserve']);
});

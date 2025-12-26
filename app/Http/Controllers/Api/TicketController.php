<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Reservation;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    /**
     * Reserve a ticket for an event.
     *
     * @param Request $request
     * @param [type] $eventId
     * @return void
     */
    public function reserve(Request $request, $eventId)
    {
        // 本当ならユーザーが存在するかEventに予約の終了時間などを角にするが省略します。
        $event = Event::findOrFail($eventId);

        $currentReservations = Reservation::where('event_id', $event->id)->count();

        if ($currentReservations < $event->total_seats) {

            $reservation = Reservation::create([
                'event_id' => $event->id,
                'user_id' => $request->input('user_id'),
                'reserved_at' => now(),
            ]);

            return response()->json([
                'message' => 'reservation successful',
                'reservation_id' => $reservation->id
            ], 201);
        }

        return response()->json([
            'message' => 'fully booked',
        ], 409);
    }
}

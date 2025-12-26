<?php

namespace App\Actions\Event;

use App\Enums\ReservationStatus;
use App\Models\Event;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;

class ReservePendingAction
{
    public function execute(int $eventId, int $userId): Reservation
    {
        return DB::transaction(function () use ($eventId, $userId) {
            $event = Event::lockForUpdate()->findOrFail($eventId);

            $currentReservations = Reservation::where('event_id', $event->id)
                ->where(function ($query) {
                    $query->where('status', ReservationStatus::CONFIRMED)
                        ->orWhere(function ($q) {
                            $q->where('status', ReservationStatus::PENDING)
                                ->where('expires_at', '>', now());
                        });
                })->count();

            if ($currentReservations >= $event->total_seats) {
                throw new \Exception('満席です', 409);
            }

            return Reservation::create([
                'event_id' => $event->id,
                'user_id' => $userId,
                'reserved_at' => now(),
                'status' => ReservationStatus::PENDING,
                'expires_at' => now()->addMinutes(5),
            ]);
        });
    }
}

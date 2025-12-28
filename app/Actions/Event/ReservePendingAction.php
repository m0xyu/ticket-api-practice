<?php

namespace App\Actions\Event;

use App\Enums\Errors\ReservationError;
use App\Enums\ReservationStatus;
use App\Exceptions\ReservationException;
use App\Models\Event;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;

class ReservePendingAction
{
    public function execute(int $eventId, int $userId): Reservation
    {
        return DB::transaction(function () use ($eventId, $userId) {
            $event = Event::lockForUpdate()->findOrFail($eventId);

            /** @var Reservation|null $myReservation */
            $myReservation = Reservation::eventAndUser($event->id, $userId)->first();
            if ($myReservation) {
                if ($myReservation->isConfirmed()) {
                    throw new ReservationException(ReservationError::ALREADY_CONFIRMED);
                }

                if (!$myReservation->isInvalid()) {
                    return $myReservation;
                }
            }

            $currentReservations = Reservation::where('event_id', $event->id)
                ->active()
                ->count();

            if ($currentReservations >= $event->total_seats) {
                throw new ReservationException(ReservationError::SEATS_FULL);
            }

            return Reservation::updateOrCreate([
                'event_id' => $event->id,
                'user_id' => $userId,
            ], [
                'reserved_at' => now(),
                'status' => ReservationStatus::PENDING,
                'expires_at' => now()->addMinutes(5),
            ]);
        });
    }
}

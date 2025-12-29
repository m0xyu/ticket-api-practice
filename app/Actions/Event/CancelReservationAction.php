<?php

namespace App\Actions\Event;

use App\Enums\Errors\ReservationError;
use App\Enums\ReservationStatus;
use App\Exceptions\ReservationException;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;

class CancelReservationAction
{
    /**
     * 予約をキャンセルするビジネスロジック
     *
     * @param int $reservationId
     * @param int $userId
     * @return Reservation
     * @throws ReservationException
     */
    public function execute(int $reservationId, int $userId): Reservation
    {
        return DB::transaction(function () use ($reservationId, $userId) {
            /** @var Reservation $reservation */
            $reservation = Reservation::lockForUpdate()->findOrFail($reservationId);

            if (!$reservation->isOwnedBy($userId)) {
                throw new ReservationException(ReservationError::UNAUTHORIZED);
            }

            if ($reservation->isCanceled()) {
                return $reservation;
            }

            if ($reservation->event->isStarted()) {
                throw new ReservationException(ReservationError::CANCELLATION_NOT_ALLOWED);
            }

            $reservation->update([
                'status' => ReservationStatus::CANCELED,
                'canceled_at' => now(),
            ]);

            return $reservation;
        });
    }
}

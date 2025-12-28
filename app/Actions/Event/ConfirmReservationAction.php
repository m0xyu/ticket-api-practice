<?php

namespace App\Actions\Event;

use App\Enums\Errors\ReservationError;
use App\Models\Reservation;
use App\Enums\ReservationStatus;
use App\Exceptions\ReservationException;
use Illuminate\Support\Facades\DB;
use Exception;

class ConfirmReservationAction
{
    /**
     * 予約を確定するビジネスロジック
     *
     * @param int $reservationId
     * @param int $userId
     * @return Reservation
     * @throws Exception
     */
    public function execute(int $reservationId, int $userId): Reservation
    {
        return DB::transaction(function () use ($reservationId, $userId) {
            /** @var Reservation $reservation */
            $reservation = Reservation::lockForUpdate()->findOrFail($reservationId);

            if (!$reservation->isOwnedBy($userId)) {
                throw new ReservationException(ReservationError::UNAUTHORIZED);
            }

            if (
                $reservation->isInvalid()
            ) {
                // 実務ではここで決済キャンセルの処理などを挟む
                throw new ReservationException(ReservationError::EXPIRED_OR_CANCELED);
            }

            if ($reservation->isConfirmed()) {
                return $reservation;
            }

            $reservation->update([
                'status' => ReservationStatus::CONFIRMED,
                'reserved_at' => now(),
                'expires_at' => null,
            ]);

            return $reservation;
        });
    }
}

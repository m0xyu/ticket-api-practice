<?php

namespace App\Actions\Event;

use App\Models\Reservation;
use App\Enums\ReservationStatus;
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
            $reservation = Reservation::lockForUpdate()->findOrFail($reservationId);

            if ($reservation->user_id !== $userId) {
                throw new Exception('この予約を確定する権限がありません', 403);
            }

            if ($reservation->status === ReservationStatus::CONFIRMED) {
                return $reservation;
            }

            if (
                $reservation->status === ReservationStatus::CANCELED ||
                ($reservation->status === ReservationStatus::PENDING && $reservation->expires_at < now())
            ) {
                // 実務ではここで決済キャンセルの処理などを挟む
                throw new Exception('有効期限切れです。再度予約してください。', 400);
            }

            $reservation->update([
                'status' => ReservationStatus::CONFIRMED,
                'confirmed_at' => now(),
                'expires_at' => null,
            ]);

            return $reservation;
        });
    }
}

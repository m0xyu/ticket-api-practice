<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    /**
     * 【脆弱な実装】 Phase 1
     * 排他制御がないため、Race Conditionによりオーバーブッキングが発生する
     */
    public function reserveUnsafe(Request $request, int $eventId)
    {
        $event = Event::findOrFail($eventId);

        // 現在の予約数をカウント
        $currentReservations = Reservation::where('event_id', $event->id)->count();

        // 空席チェック
        if ($currentReservations < $event->total_seats) {

            // 競合状態を再現しやすくするためのウェイト
            usleep(200000); // 0.2秒

            $reservation = Reservation::create([
                'event_id' => $event->id,
                'user_id' => $request->input('user_id'),
                'reserved_at' => now(),
            ]);

            return response()->json([
                'message' => '予約完了しました (Unsafe)',
                'reservation_id' => $reservation->id
            ], 201);
        }

        return response()->json(['message' => '満席です'], 409);
    }

    public function reserveSecure(Request $request, int $eventId)
    {

        // トランザクション開始
        return DB::transaction(function () use ($eventId, $request) {
            // lockForUpdateで取得したデータがデータベース内で変更されないことを保証する
            $event = Event::lockForUpdate()->findOrFail($eventId);
            $currentReservations = Reservation::where('event_id', $event->id)->count();

            if ($currentReservations < $event->total_seats) {
                usleep(200000);

                $reservation = Reservation::create([
                    'event_id' => $event->id,
                    'user_id' => $request->input('user_id'),
                    'reserved_at' => now(),
                ]);

                return response()->json([
                    'message' => '予約完了しました (Secure)',
                    'reservation_id' => $reservation->id
                ], 201);
            }
            return response()->json(['message' => '満席です'], 409);
        });
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Reservation;
use App\Enums\ReservationStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

    /**
     * 
     * 【安全な実装】 Phase 2
     * データベースの排他制御（行ロック）を用いてオーバーブッキングを防止
     * しているが決済処理が含まれるとロック時間が長くなりパフォーマンスが低下する
     */
    public function reserveSecure(Request $request, int $eventId)
    {

        // トランザクション開始
        return DB::transaction(function () use ($eventId, $request) {
            // lockForUpdateで取得したデータがデータベース内で変更されないことを保証する
            $event = Event::lockForUpdate()->findOrFail($eventId);
            $currentReservations = Reservation::where('event_id', $event->id)->count();

            if ($currentReservations < $event->total_seats) {
                // 本来なら決済API呼び出しなどの処理がここに入る
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

    /**
     * * 仮予約エンドポイント (Phase 3)
     * データベースの排他制御（行ロック）を用いてオーバーブッキングを防止
     * 仮予約(Pending)ステータスで席を確保し、一定時間内に確定しない場合は自動的に解放
     * ミドルウェアで冪等性を保証
     * 
     */
    public function reservePending(int $eventId)
    {
        return DB::transaction(function () use ($eventId) {
            $event = Event::lockForUpdate()->findOrFail($eventId);

            // 有効な予約数をカウント
            $currentReservations = Reservation::where('event_id', $event->id)
                ->where(function ($query) {
                    $query->where('status', ReservationStatus::CONFIRMED)
                        ->orWhere(function ($q) {
                            $q->where('status', ReservationStatus::PENDING)
                                ->where('expires_at', '>', now());
                        });
                })->count();

            if ($currentReservations < $event->total_seats) {

                $reservation = Reservation::create([
                    'event_id' => $event->id,
                    'user_id' => Auth::id(),
                    'reserved_at' => now(),
                    'status' => ReservationStatus::PENDING,
                    'expires_at' => now()->addMinutes(5), // 5分間の有効期限
                ]);

                return response()->json([
                    'message' => '席を押さえました 5分以内に決済してください (Pending)',
                    'reservation_id' => $reservation->id,
                    'expires_at' => $reservation->expires_at,
                ], 201);
            }
            // キャンセル待ちなどの追加ロジックも考慮する必要があります 今回は省く
            return response()->json(['message' => '満席です（仮押さえ含む）'], 409);
        });
    }

    /**
     * 予約確定エンドポイント (Phase 3)
     * 仮予約を確定に変更し、期限切れやキャンセルの場合はエラーを返す
     * 決済Apiからのコールバックで呼ばれる想定
     * ミドルウェアで冪等性を保証
     */
    public function confirmReservation(int $reservationId)
    {
        return DB::transaction(function () use ($reservationId) {
            $reservation = Reservation::lockForUpdate()->findOrFail($reservationId);

            if ($reservation->user_id !== Auth::id()) {
                return response()->json(['message' => 'この予約を確定する権限がありません'], 403);
            }

            if ($reservation->status === ReservationStatus::CONFIRMED) {
                return response()->json(['message' => 'すでに確定済みです'], 200);
            }

            if (
                $reservation->status === ReservationStatus::CANCELED ||
                ($reservation->status === ReservationStatus::PENDING && $reservation->expires_at < now())
            ) {
                // ここで返金処理(Refund)を呼ぶ必要がある
                return response()->json(['message' => '有効期限切れです。再度予約してください。'], 400);
            }

            $reservation->update([
                'status' => ReservationStatus::CONFIRMED,
                'confirmed_at' => now(),
                'expires_at' => null,
            ]);

            return response()->json([
                'message' => '予約が確定しました',
                'reservation_id' => $reservation->id
            ], 200);
        });
    }
}

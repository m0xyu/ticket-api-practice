<?php

namespace App\Http\Controllers\Api;

use App\Actions\Event\ConfirmReservationAction;
use App\Actions\Event\ReservePendingAction;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class TicketController extends Controller
{
    /**
     * 【脆弱な実装】 Phase 1
     * 排他制御がないため、Race Conditionによりオーバーブッキングが発生する
     */
    // public function reserveUnsafe(Request $request, int $eventId)
    // {
    //     $event = Event::findOrFail($eventId);

    //     // 現在の予約数をカウント
    //     $currentReservations = Reservation::where('event_id', $event->id)->count();

    //     // 空席チェック
    //     if ($currentReservations < $event->total_seats) {

    //         // 競合状態を再現しやすくするためのウェイト
    //         usleep(200000); // 0.2秒

    //         $reservation = Reservation::create([
    //             'event_id' => $event->id,
    //             'user_id' => $request->input('user_id'),
    //             'reserved_at' => now(),
    //         ]);

    //         return response()->json([
    //             'message' => '予約完了しました (Unsafe)',
    //             'reservation_id' => $reservation->id
    //         ], 201);
    //     }

    //     return response()->json(['message' => '満席です'], 409);
    // }

    /**
     * 
     * 【安全な実装】 Phase 2
     * データベースの排他制御（行ロック）を用いてオーバーブッキングを防止
     * しているが決済処理が含まれるとロック時間が長くなりパフォーマンスが低下する
     */
    // public function reserveSecure(Request $request, int $eventId)
    // {

    //     // トランザクション開始
    //     return DB::transaction(function () use ($eventId, $request) {
    //         // lockForUpdateで取得したデータがデータベース内で変更されないことを保証する
    //         $event = Event::lockForUpdate()->findOrFail($eventId);
    //         $currentReservations = Reservation::where('event_id', $event->id)->count();

    //         if ($currentReservations < $event->total_seats) {
    //             // 本来なら決済API呼び出しなどの処理がここに入る
    //             usleep(200000);

    //             $reservation = Reservation::create([
    //                 'event_id' => $event->id,
    //                 'user_id' => $request->input('user_id'),
    //                 'reserved_at' => now(),
    //             ]);

    //             return response()->json([
    //                 'message' => '予約完了しました (Secure)',
    //                 'reservation_id' => $reservation->id
    //             ], 201);
    //         }
    //         return response()->json(['message' => '満席です'], 409);
    //     });
    // }

    /**
     * * 仮予約エンドポイント (Phase 3)
     * データベースの排他制御（行ロック）を用いてオーバーブッキングを防止
     * 仮予約(Pending)ステータスで席を確保し、一定時間内に確定しない場合は自動的に解放
     * ミドルウェアで冪等性を保証
     * 
     */
    public function reservePending(int $eventId, ReservePendingAction $action)
    {
        $reservation = $action->execute($eventId, Auth::id());
        return response()->json([
            'message' => '仮予約が完了しました',
            'reservation_id' => $reservation->id,
            'expires_at' => $reservation->expires_at,
        ], 201);
    }

    /**
     * 予約確定エンドポイント (Phase 3)
     * 仮予約を確定に変更し、期限切れやキャンセルの場合はエラーを返す
     * 決済Apiからのコールバックで呼ばれる想定
     * ミドルウェアで冪等性を保証
     */
    public function confirmReservation(int $reservationId, ConfirmReservationAction $action)
    {
        $reservation = $action->execute($reservationId, Auth::id());
        return response()->json([
            'message' => '予約が確定しました',
            'reservation_id' => $reservation->id
        ], 200);
    }
}

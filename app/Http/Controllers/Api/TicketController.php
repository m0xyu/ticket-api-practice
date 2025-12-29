<?php

namespace App\Http\Controllers\Api;

use App\Actions\Event\CancelReservationAction;
use App\Actions\Event\ConfirmReservationAction;
use App\Actions\Event\ReservePendingAction;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Header;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\UrlParam;

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
     * 仮予約
     * * 座席を確保し、仮予約状態（PENDING）を作成します。
     * 成功すると `201 Created` が返されます。
     * * <aside class="notice">
     * <strong>冪等性キーについて</strong><br>
     * このエンドポイントは <code>Idempotency-Key</code> ヘッダーに対応しています。<br>
     * ネットワークエラー時の二重予約を防ぐため、UUIDなどの一意なキーをヘッダーに含めることを推奨します。
     * </aside>
     * * @param int $eventId
     * @param ReservePendingAction $action
     * @return \Illuminate\Http\JsonResponse
     */
    #[Group('予約管理')]
    #[Authenticated()]
    #[Header("Idempotency-Key", "test-key-001")]
    #[UrlParam(name: 'eventId', description: '予約するイベントのID', example: 1, type: 'integer')]
    #[Response([
        'message' => '仮予約が完了しました',
        "reservation_id" => 1,
        "expires_at" => "2025-12-29T10:00:00.000000Z"
    ], 201, "成功時")]
    #[Response([
        "message" => "すでに予約済みです",
        "error_code" => "already_confirmed"
    ], 409, "すでに予約済み")]
    #[Response([
        "message" => "満席です",
        "error_code" => "seats_full"
    ], 409, "満席です")]
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
     * 予約確定
     * * 仮予約（PENDING）を確定（CONFIRMED）に変更します。
     * 決済完了後に呼び出してください。
     * * <aside class="notice">
     * <strong>冪等性キー（必須）</strong><br>
     * 決済確定処理の二重実行を防ぐため、このエンドポイントでは <code>Idempotency-Key</code> が必須です。<br>
     * </aside>
     * * @param int $reservationId
     * @param ConfirmReservationAction $action
     * @return \Illuminate\Http\JsonResponse
     */
    #[Group('予約管理')]
    #[Authenticated()]
    #[Header("Idempotency-Key", "test-confirm-002")]
    #[UrlParam(name: 'reservationId', description: '仮予約中の予約のID', example: 1, type: 'integer')]
    #[Response([
        'message' => '予約が確定しました',
        "reservation_id" => 1,
    ], 200, "成功時")]
    #[Response([
        "message" => "有効期限切れです。再度予約してください。",
        "error_code" => "expired_or_canceled"
    ], 400, "仮予約の有効期限切れ")]
    #[Response([
        "message" => "この予約を確定する権限がありません",
        "error_code" => "unauthorized"
    ], 403, "権限エラー")]
    public function confirmReservation(int $reservationId, ConfirmReservationAction $action)
    {
        $reservation = $action->execute($reservationId, Auth::id());
        return response()->json([
            'message' => '予約が確定しました',
            'reservation_id' => $reservation->id
        ], 200);
    }

    /**
     * 予約キャンセルエンドポイント 
     * 
     * @param int $reservationId
     * @param CancelReservationAction $action
     * @return \Illuminate\Http\JsonResponse
     */
    #[Group('予約管理')]
    #[Authenticated()]
    #[UrlParam(name: 'reservationId', description: 'キャンセルする予約のID', example: 1, type: 'integer')]
    #[Response([
        "id" => 1,
        "status" => "canceled",
        "canceled_at" => "2025-12-29T10:00:00.000000Z"
    ], 200, "成功時")]
    #[Response([
        "message" => "イベント開始後のためキャンセルできません",
        "error_code" => "cancellation_not_allowed"
    ], 400, "イベント開始後")]
    #[Response([
        "message" => "この予約を確定する権限がありません",
        "error_code" => "unauthorized"
    ], 403, "権限エラー")]
    public function cancelReservation(int $reservationId, CancelReservationAction $action)
    {
        $reservation = $action->execute($reservationId, Auth::id());
        return response()->json([
            'message' => '予約がキャンセルされました',
            'reservation_id' => $reservation->id,
            'canceled_at' => $reservation->canceled_at,
        ], 200);
    }
}

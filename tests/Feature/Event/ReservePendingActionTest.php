<?php

use App\Actions\Event\ReservePendingAction;
use App\Enums\ReservationStatus;
use App\Models\Event;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('ReservePendingAction', function () {
    beforeEach(function () {
        $this->action = new ReservePendingAction();
    });

    it('正常に仮予約が作成されること', function () {
        $event = Event::factory()->create([
            'name' => 'テストイベント',
            'total_seats' => 10
        ]);
        $user = User::factory()->create();

        $reservation = $this->action->execute($event->id, $user->id);

        expect($reservation)->toBeInstanceOf(Reservation::class);
        expect($reservation->event_id)->toEqual($event->id);
        expect($reservation->status)->toEqual(ReservationStatus::PENDING);
        $this->assertDatabaseCount('reservations', 1);
    });

    it('異常系: 満席の場合は例外が発生すること', function () {
        $event = Event::factory()->create([
            'name' => '満席イベント',
            'total_seats' => 2
        ]);
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        // 事前に2件の確定予約を作成して満席状態にする
        Reservation::factory()->create([
            'event_id' => $event->id,
            'user_id' => $user1->id,
            'status' => ReservationStatus::CONFIRMED,
            'reserved_at' => now(),
        ]);
        Reservation::factory()->create([
            'event_id' => $event->id,
            'user_id' => $user2->id,
            'status' => ReservationStatus::CONFIRMED,
            'reserved_at' => now(),
        ]);

        expect(fn() => $this->action->execute($event->id, $user3->id))
            ->toThrow(Exception::class, '満席です')
            ->toThrow(function (Exception $e) {
                return $e->getCode() === 409;
            });
    });
});

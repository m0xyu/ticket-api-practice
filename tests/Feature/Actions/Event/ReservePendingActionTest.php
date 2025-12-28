<?php

namespace Tests\Feature\Actions\Event;

use App\Actions\Event\ReservePendingAction;
use App\Enums\Errors\ReservationError;
use App\Enums\ReservationStatus;
use App\Exceptions\ReservationException;
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
            ->toThrow(ReservationException::class, ReservationError::SEATS_FULL->message())
            ->toThrow(function (ReservationException $e) {
                return $e->getCode() === ReservationError::SEATS_FULL->status();
            });
    });

    it('異常系: すでに確定予約がある場合は例外が発生すること', function () {
        $event = Event::factory()->create([
            'name' => '確定予約があるイベント',
            'total_seats' => 10
        ]);
        $user = User::factory()->create();

        Reservation::factory()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'status' => ReservationStatus::CONFIRMED,
            'reserved_at' => now(),
        ]);

        expect(fn() => $this->action->execute($event->id, $user->id))
            ->toThrow(ReservationException::class, ReservationError::ALREADY_CONFIRMED->message())
            ->toThrow(function (ReservationException $e) {
                return $e->getCode() === ReservationError::ALREADY_CONFIRMED->status();
            });
    });

    it(
        'pendingまたはexpires_atが期限内の予約がある場合は既存の予約が返されること',
        function () {
            $event = Event::factory()->create([
                'name' => '既存の予約があるイベント',
                'total_seats' => 10
            ]);
            $user = User::factory()->create();

            Reservation::factory()->create([
                'event_id' => $event->id,
                'user_id' => $user->id,
                'status' => ReservationStatus::PENDING,
                'reserved_at' => now(),
                'expires_at' => now()->addMinutes(3),
            ]);

            $reservation = $this->action->execute($event->id, $user->id);

            expect($reservation)->toBeInstanceOf(Reservation::class);
            expect($reservation->event_id)->toEqual($event->id);
        }
    );

    it('canceledまたはexpiredな予約がある場合は既存の予約が更新されること', function () {
        $event = Event::factory()->create([
            'name' => 'expiredな予約があるイベント',
            'total_seats' => 10
        ]);
        $user = User::factory()->create();

        $reservation =  Reservation::factory()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'status' => ReservationStatus::CANCELED,
            'reserved_at' => now()->subMinutes(10),
            'expires_at' => now()->subMinutes(5),
        ]);

        $result = $this->action->execute($event->id, $user->id);

        expect($result)->toBeInstanceOf(Reservation::class);
        expect($result->id)->toBe($reservation->id);
        expect($result->event_id)->toEqual($event->id);
        expect($result->status)->toEqual(ReservationStatus::PENDING);
        expect($result->expires_at->gt(now()))->toBeTrue();
        $this->assertDatabaseCount('reservations', 1);
    });
});

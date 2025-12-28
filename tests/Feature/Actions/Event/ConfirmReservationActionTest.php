<?php

namespace Tests\Feature\Actions\Event;

use App\Actions\Event\ConfirmReservationAction;
use App\Enums\Errors\ReservationError;
use App\Enums\ReservationStatus;
use App\Exceptions\ReservationException;
use App\Models\Event;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('ConfirmReservationAction', function () {
    beforeEach(function () {
        $this->action = new ConfirmReservationAction();
    });

    it('正常に予約が確定されること', function () {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'name' => 'テストイベント',
            'total_seats' => 10
        ]);
        $reservation = Reservation::factory()->create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'status' => ReservationStatus::PENDING,
            'expires_at' => now()->addMinutes(15),
            'reserved_at' => now(),
        ]);

        $confirmedReservation = $this->action->execute($reservation->id, $user->id);

        expect($confirmedReservation)->toBeInstanceOf(Reservation::class);
        expect($confirmedReservation->status)->toEqual(ReservationStatus::CONFIRMED);
        expect($confirmedReservation->reserved_at)->not->toBeNull();
        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => ReservationStatus::CONFIRMED,
            'reserved_at' => $confirmedReservation->reserved_at,
        ]);
    });

    it('異常系: 他ユーザーの予約を確定しようとすると例外が発生すること', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $event = Event::factory()->create([
            'name' => 'テストイベント',
            'total_seats' => 10
        ]);
        $reservation = Reservation::factory()->create([
            'user_id' => $user1->id,
            'event_id' => $event->id,
            'status' => ReservationStatus::PENDING,
            'expires_at' => now()->addMinutes(15),
            'reserved_at' => now(),
        ]);

        expect(fn() => $this->action->execute($reservation->id, $user2->id))
            ->toThrow(ReservationException::class, ReservationError::UNAUTHORIZED->message())
            ->toThrow(function (ReservationException $e) {
                return $e->getCode() === ReservationError::UNAUTHORIZED->status();
            });
    });

    it('異常系: 期限切れの予約を確定しようとすると例外が発生すること', function () {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'name' => 'テストイベント',
            'total_seats' => 10
        ]);
        $reservation = Reservation::factory()->create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'status' => ReservationStatus::PENDING,
            'expires_at' => now()->subMinutes(1), // 期限切れ
            'reserved_at' => now(),
        ]);

        expect(fn() => $this->action->execute($reservation->id, $user->id))
            ->toThrow(ReservationException::class, ReservationError::EXPIRED_OR_CANCELED->message())
            ->toThrow(function (ReservationException $e) {
                return $e->getCode() === ReservationError::EXPIRED_OR_CANCELED->status();
            });
    });
});

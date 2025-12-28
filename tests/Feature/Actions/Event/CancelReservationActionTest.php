<?php

namespace App\Actions\Event;

use App\Enums\Errors\ReservationError;
use App\Enums\ReservationStatus;
use App\Exceptions\ReservationException;
use App\Models\Event;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('CancelReservationAction', function () {
    beforeEach(function () {
        $this->action = new CancelReservationAction();
    });

    it('ユーザーが自分の予約をキャンセルできること', function () {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'name' => 'Sample Event',
            'total_seats' => 100,
            'start_at' => now()->addDays(5),
        ]);
        $reservation = Reservation::factory()->create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'status' => ReservationStatus::CONFIRMED,
            'reserved_at' => now(),
        ]);

        $result = $this->action->execute($reservation->id, $user->id);

        expect($result->status)->toBe(ReservationStatus::CANCELED);
        expect($result->canceled_at)->not->toBeNull();
    });

    it('異常系:他のユーザーの予約をキャンセルしようとすると例外が投げられること', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $event = Event::factory()->create([
            'name' => 'Sample Event',
            'total_seats' => 100,
            'start_at' => now()->addDays(5),
        ]);
        $reservation = Reservation::factory()->create([
            'user_id' => $user1->id,
            'event_id' => $event->id,
            'status' => ReservationStatus::CONFIRMED,
            'reserved_at' => now(),
        ]);

        expect(fn() => $this->action->execute($reservation->id, $user2->id))
            ->toThrow(ReservationException::class, ReservationError::UNAUTHORIZED->message())
            ->toThrow(function (ReservationException $e) {
                return $e->getCode() === ReservationError::UNAUTHORIZED->status();
            });
    });

    it('異常系:イベント開始後の予約をキャンセルしようとすると例外が投げられること', function () {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'name' => 'Sample Event',
            'total_seats' => 100,
            'start_at' => now()->subHours(1),
        ]);
        $reservation = Reservation::factory()->create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'status' => ReservationStatus::CONFIRMED,
            'reserved_at' => now()->subDays(2),
        ]);

        expect(fn() => $this->action->execute($reservation->id, $user->id))
            ->toThrow(ReservationException::class, ReservationError::CANCELLATION_NOT_ALLOWED->message())
            ->toThrow(function (ReservationException $e) {
                return $e->getCode() === ReservationError::CANCELLATION_NOT_ALLOWED->status();
            });
    });

    it('すでにキャンセル済みの予約をキャンセルしようした場合既存の予約を返すこと', function () {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'name' => 'Sample Event',
            'total_seats' => 100,
            'start_at' => now()->addDays(5),
        ]);
        $reservation = Reservation::factory()->create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'status' => ReservationStatus::CANCELED,
            'reserved_at' => now(),
            'canceled_at' => now()->subDays(1),
        ]);

        $result = $this->action->execute($reservation->id, $user->id);

        expect($result->id)->toBe($reservation->id);
        expect($result->status)->toBe(ReservationStatus::CANCELED);
        expect($result->canceled_at)->not->toBeNull();
    });
});

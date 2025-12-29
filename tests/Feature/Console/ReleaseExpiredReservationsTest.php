<?php

use App\Models\Event;
use App\Models\Reservation;
use App\Models\User;
use App\Enums\ReservationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('期限切れの仮予約を解放（EXPIREDに変更）できること', function () {
    $user = User::factory()->create();
    $event1 = Event::factory()->create([
        'id' => 1,
        'name' => 'テストイベント',
        'total_seats' => 10
    ]);

    $event2 = Event::factory()->create([
        'id' => 2,
        'name' => 'テストイベント',
        'total_seats' => 10
    ]);

    $expiredReservation = Reservation::factory()->create([
        'user_id' => $user->id,
        'event_id' => $event1->id,
        'status' => ReservationStatus::PENDING,
        'reserved_at' => now(),
        'expires_at' => now()->subMinute(), // 過去
    ]);

    $activeReservation = Reservation::factory()->create([
        'user_id' => $user->id,
        'event_id' => $event2->id,
        'status' => ReservationStatus::PENDING,
        'reserved_at' => now(),
        'expires_at' => now()->addMinute(), // 未来
    ]);

    $this->artisan('reservations:release-expired')
        ->assertSuccessful()
        ->expectsOutputToContain('1 件の期限切れ仮予約を解放しました。');

    $this->assertDatabaseHas('reservations', [
        'id' => $expiredReservation->id,
        'event_id' => $event1->id,
        'status' => ReservationStatus::EXPIRED,
    ]);

    $this->assertDatabaseHas('reservations', [
        'id' => $activeReservation->id,
        'event_id' => $event2->id,
        'status' => ReservationStatus::PENDING,
    ]);
});

<?php

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\{actingAs};


uses(RefreshDatabase::class);

describe('API: Reserve Pending', function () {

    it('正常系: 201 Created が返り、予約IDが含まれる', function () {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'name' => 'テストイベント',
            'total_seats' => 10
        ]);

        $response = actingAs($user)
            ->withHeader('Idempotency-Key', uniqid())
            ->postJson("/api/v1/events/{$event->id}/reserve-pending");
        $response->assertStatus(201)
            ->assertJson([
                'message' => '仮予約が完了しました',
                'data' => [
                    'id' => $response->json('data.id'),
                    'event_id' => $event->id,
                    'status' => 'pending',
                    'expires_at' => $response->json('data.expires_at'),
                ]
            ]);
    });

    it('異常系: 存在しないイベントIDなら 404 Not Found', function () {
        $user = User::factory()->create();

        actingAs($user)
            ->withHeader('Idempotency-Key', uniqid())
            ->postJson("/api/v1/events/999999/reserve-pending")
            ->assertStatus(404);
    });

    it('異常系: 満席なら 409 Conflict', function () {
        $user = User::factory()->create();
        // 0席のイベント
        $event = Event::factory()->create([
            'name' => 'テストイベント',
            'total_seats' => 0
        ]);

        actingAs($user)
            ->withHeader('Idempotency-Key', uniqid())
            ->postJson("/api/v1/events/{$event->id}/reserve-pending")
            ->assertStatus(409)
            ->assertJson(['message' => '満席です']);
    });

    it('confirmReservationのテスト', function () {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'name' => 'テストイベント',
            'total_seats' => 10
        ]);

        // まず仮予約を作成
        $reserveResponse = actingAs($user)
            ->withHeader('Idempotency-Key', uniqid())
            ->postJson("/api/v1/events/{$event->id}/reserve-pending");
        $reserveResponse->assertStatus(201);
        $reservationId = $reserveResponse->json('data.id');

        // 仮予約を確定
        $confirmResponse = actingAs($user)
            ->withHeader('Idempotency-Key', uniqid())
            ->postJson("/api/v1/reservations/{$reservationId}/confirm");
        $confirmResponse->assertStatus(200)
            ->assertJson([
                'message' => '予約が確定しました',
                'data' => [
                    'status' => 'confirmed',
                    'expires_at' => null,
                ]
            ]);
    });

    it('ccancelReservationのテスト', function () {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'name' => 'テストイベント',
            'total_seats' => 10
        ]);

        // まず仮予約を作成
        $reserveResponse = actingAs($user)
            ->withHeader('Idempotency-Key', uniqid())
            ->postJson("/api/v1/events/{$event->id}/reserve-pending");
        $reserveResponse->assertStatus(201);
        $reservationId = $reserveResponse->json('data.id');

        // 仮予約をキャンセル
        $cancelResponse = actingAs($user)
            ->postJson("/api/v1/reservations/{$reservationId}/cancel");
        $cancelResponse->assertStatus(200)
            ->assertJson([
                'message' => '予約がキャンセルされました',
                'data' => [
                    'id' => $reservationId,
                    'status' => 'canceled',
                ]
            ]);
    });
});

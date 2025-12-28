<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\{postJson, assertGuest, assertAuthenticatedAs, actingAs};

uses(RefreshDatabase::class);

describe('LoginController', function () {
    it('正常系: 有効な資格情報でログインできること', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password')
        ]);
        $response = postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ], [
            'Referer' => 'http://localhost',
        ]);
        $response->assertStatus(200)
            ->assertJson(['message' => 'Authenticated']);
        assertAuthenticatedAs($user);
    });

    it('異常系: 無効な資格情報でログインできないこと', function () {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password')
        ]);
        $response = postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'invalid',
        ], [
            'Referer' => 'http://localhost',
        ]);
        $response->assertStatus(422)
            ->assertJsonStructure([
                'errors' => [
                    'email'
                ]
            ]);
        assertGuest();
    });

    it('正常系: ログアウトできること', function () {
        $user = User::factory()->create();
        actingAs($user, 'web');

        $response = postJson('/api/v1/logout', [], [
            'Referer' => 'http://localhost',
        ]);
        $response->dump();
        $response->assertStatus(200)
            ->assertJson(['message' => 'Logged out']);
        assertGuest('web');
    });
});

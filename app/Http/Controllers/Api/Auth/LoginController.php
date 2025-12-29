<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;

class LoginController extends Controller
{
    /**
     * ログイン
     * * SPA認証用のログイン処理です。
     * 成功すると `laravel_session` クッキーが発行されます。
     */
    #[Group("認証")]
    #[BodyParam("email", "ユーザーのメールアドレス", required: true, example: "test@example.com")]
    #[BodyParam("password", "ユーザーのパスワード", required: true, example: "password")]
    #[Response(["message" => "Authenticated."], 200, "成功時（Cookie発行）")]
    #[Response([
        "errors" => [
            "email" => ["The provided credentials do not match our records."]
        ]
    ], 422, "ログイン失敗")]
    public function authenticate(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();

            return response()->json(['message' => 'Authenticated'], 200);
        }

        return response()->json([
            'errors' => [
                'email' => ['The provided credentials do not match our records.'],
            ],
        ], 422);
    }

    /**
     * ログアウト
     * * SPA認証用のログアウト処理です。
     * `laravel_session` クッキーを無効化します。
     */
    #[Group("認証")]
    #[Authenticated()]
    #[Response(["message" => "Logged out"], 200, "成功時（Cookie無効化）")]
    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out'], 200);
    }
}

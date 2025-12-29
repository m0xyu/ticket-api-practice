<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // ヘッダー名: Idempotency-Key (フロントエンドで生成して付与する想定)
        $key = $request->header('Idempotency-Key');

        if (!$key) {
            return response()->json(['message' => 'Idempotency-Key ヘッダーが必要です'], 400);
        }

        $userId = $request->user() ? $request->user()->id : 'guest';
        $cacheKey = "idempotency:{$userId}:{$key}";
        $lockKey = "lock:idempotency:{$userId}:{$key}";

        if (Cache::has($cacheKey)) {
            $response = Cache::get($cacheKey);
            return $response->header('X-Idempotency-Replay', 'true');
        }

        $lock = Cache::lock($lockKey, 10);

        if (!$lock->get()) {
            // ロックが取れない＝現在、同じキーで処理を実行中
            return response()->json([
                'message' => '同じIdempotency-Keyでのリクエストが処理中です。しばらく待ってから再度お試しください。'
            ], 429);
        }

        try {
            $response =  $next($request);

            if ($response->isSuccessful()) {
                Cache::put($cacheKey, $response, now()->addHours(24));
            }

            return $response;
        } finally {
            $lock->release();
        }
    }
}

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
        $idempotencyKey = $request->header('Idempotency-Key');

        if (!$idempotencyKey) {
            return response()->json(['message' => 'Idempotency-Key ヘッダーが必要です'], 400);
        }

        $userId = $request->user() ? $request->user()->id : 'guest';
        $cacheKey = "idempotency:{$userId}:{$idempotencyKey}";

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $response =  $next($request);

        if ($response->isSuccessful()) {
            Cache::put($cacheKey, $response, now()->addHours(24));
        }

        return $response;
    }
}

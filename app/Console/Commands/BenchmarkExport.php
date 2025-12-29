<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Reservation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BenchmarkExport extends Command
{
    protected $signature = 'benchmark:export {count=10000 : 計測に使用するデータ数}';
    protected $description = '通常取得と最適化取得のパフォーマンス比較（メモリ・時間）を行います';

    public function handle()
    {
        $limit = (int) $this->argument('count');
        $this->info("🚀 ベンチマーク開始（対象: {$limit}件）");

        $total = Reservation::count();
        if ($total < $limit) {
            $this->warn("データが足りないため、一時的にファクトリで作成します...");

            $missing = $limit - $total;

            // 1. 紐付け用のイベントを1つ用意（なければ作る）
            $event = Event::first() ?? Event::factory()->create([
                'name' => 'ベンチマーク用イベント',
                'total_seats' => $missing + 1000
            ]);

            $this->info("イベントID: {$event->id} に {$missing} 件の予約データを追加生成中...");

            // 2. プログレスバーを表示（数万件作ると少し時間がかかるため親切）
            $bar = $this->output->createProgressBar($missing);
            $bar->start();

            $chunkSize = 500;
            for ($i = 0; $i < $missing; $i += $chunkSize) {
                $currentBatch = min($chunkSize, $missing - $i);

                Reservation::factory()
                    ->count($currentBatch)
                    ->for($event)
                    ->state(function (array $attributes) {
                        return ['user_id' => \App\Models\User::factory()];
                    })
                    ->create([
                        'status' => \App\Enums\ReservationStatus::CONFIRMED,
                        'reserved_at' => now(),
                    ]);

                $bar->advance($currentBatch);
            }

            $bar->finish();
            $this->newLine();
            $this->info("データの準備が完了しました。");
        }

        // ==========================================
        // 1. Normal Way (Eloquent + Eager Loading)
        // ==========================================
        $this->info('1. 通常のEloquent取得 (with User) を計測中...');

        // メモリ計測開始
        gc_collect_cycles(); // ガベージコレクション強制実行
        $startMem = memory_get_usage();
        $startTime = microtime(true);

        $normalCount = 0;
        // 普通に全件取得（メモリを大量消費するパターン）
        $reservations = Reservation::with('user')
            ->limit($limit)
            ->get();

        foreach ($reservations as $reservation) {
            $temp = [
                $reservation->id,
                $reservation->user->name,
                $reservation->status,
            ];
            $normalCount++;
        }

        $endTime = microtime(true);
        $endMem = memory_get_peak_usage(); // ピーク時メモリ

        $normalTime = $endTime - $startTime;
        $normalMemBytes = $endMem - $startMem;
        $normalMemMB = round($normalMemBytes / 1024 / 1024, 2);

        // メモリ解放
        unset($reservations);
        gc_collect_cycles();


        // ==========================================
        // 2. Optimized Way (toBase + lazyById)
        // ==========================================
        $this->info('2. 最適化取得 (toBase + lazyById) を計測中...');

        $startMem = memory_get_usage();
        $startTime = microtime(true);

        $optCount = 0;

        $query = DB::table('reservations')
            ->join('users', 'reservations.user_id', '=', 'users.id')
            ->select([
                'reservations.id',
                'users.name as user_name',
                'reservations.status',
            ])
            ->limit($limit)
            ->orderBy('reservations.id');

        // lazyByIdで少しずつ取得し、インスタンス化もしない
        foreach ($query->lazyById(1000, 'reservations.id', 'id') as $row) {
            $temp = [
                $row->id,
                $row->user_name,
                $row->status,
            ];
            $optCount++;
        }

        $endTime = microtime(true);
        $endMem = memory_get_peak_usage();


        $optTime = $endTime - $startTime;

        // ここでは「時間」をメインの成果として表示。
        $this->newLine();
        $this->table(
            ['Method', 'Time (sec)', 'Records'],
            [
                ['Normal (Eloquent)', number_format($normalTime, 4) . ' s', $normalCount],
                ['Optimized (Query)', number_format($optTime, 4) . ' s', $optCount],
            ]
        );

        $speedUp = $normalTime / $optTime;
        $this->info(sprintf("✨ 速度改善率: %.2f倍 速くなりました！", $speedUp));
    }
}

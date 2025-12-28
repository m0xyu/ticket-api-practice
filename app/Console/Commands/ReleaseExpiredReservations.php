<?php

namespace App\Console\Commands;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReleaseExpiredReservations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reservations:release-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '仮予約のステータスを期限切れに変更し、解放します';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('期限切れの仮予約の解放処理を開始します...');

        $count = DB::transaction(function () {
            return Reservation::where('status', ReservationStatus::PENDING)
                ->where('expires_at', '<', now())
                ->update(['status' => ReservationStatus::EXPIRED]);
        });

        if ($count > 0) {
            $this->info("{$count} 件の期限切れ仮予約を解放しました。");
        } else {
            $this->info('解放する期限切れ仮予約はありませんでした。');
        }
    }
}

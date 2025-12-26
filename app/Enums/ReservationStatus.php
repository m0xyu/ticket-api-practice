<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case PENDING = 'pending';     // 仮予約
    case CONFIRMED = 'confirmed'; // 確定
    case CANCELED = 'canceled';   // 期限切れ/キャンセル

    public function label(): string
    {
        return match ($this) {
            self::PENDING => '仮予約',
            self::CONFIRMED => '確定',
            self::CANCELED => '期限切れ/キャンセル',
        };
    }
}

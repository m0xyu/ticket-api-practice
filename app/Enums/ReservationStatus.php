<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case PENDING = 'pending';     // 仮予約
    case CONFIRMED = 'confirmed'; // 確定
    case CANCELED = 'canceled';   // 期限切れ/キャンセル
    case EXPIRED = 'expired';   // 期限切れ

    public function label(): string
    {
        return match ($this) {
            self::PENDING => '仮予約',
            self::CONFIRMED => '確定',
            self::CANCELED => 'キャンセル',
            self::EXPIRED => '期限切れ',
        };
    }
}

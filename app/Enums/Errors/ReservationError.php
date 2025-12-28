<?php

namespace App\Enums\Errors;

use App\Attributes\ErrorDetails;
use ReflectionEnumBackedCase;

enum ReservationError: string
{
    #[ErrorDetails('この予約を確定する権限がありません', 403)]
    case UNAUTHORIZED = 'unauthorized';

    #[ErrorDetails('有効期限切れです。再度予約してください。', 400)]
    case EXPIRED_OR_CANCELED = 'expired_or_canceled';

    #[ErrorDetails('すでに予約済みです', 409)]
    case ALREADY_CONFIRMED = 'already_confirmed';

    #[ErrorDetails('満席です', 409)]
    case SEATS_FULL = 'seats_full';

    #[ErrorDetails('イベント開始後のためキャンセルできません', 400)]
    case CANCELLATION_NOT_ALLOWED = 'cancellation_not_allowed';


    /**
     * 属性からメッセージを取得
     */
    public function message(): string
    {
        return $this->getDetails()->message;
    }

    /**
     * 属性からステータスコードを取得
     */
    public function status(): int
    {
        return $this->getDetails()->statusCode;
    }

    /**
     * リフレクションで属性(ErrorDetails)を取得する内部メソッド
     */
    private function getDetails(): ErrorDetails
    {
        $reflection = new ReflectionEnumBackedCase($this, $this->name);
        $attributes = $reflection->getAttributes(ErrorDetails::class);

        return $attributes[0]->newInstance();
    }
}

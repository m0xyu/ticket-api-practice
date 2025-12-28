<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Attributes\Scope;

/**
 * @property int $id
 * @property int $event_id
 * @property int $user_id
 * @property ReservationStatus $status 予約ステータス
 * @property \Illuminate\Support\Carbon $reserved_at
 * @property \Illuminate\Support\Carbon|null $expires_at 有効期限
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $canceled_at
 * @property-read \App\Models\Event $event
 * @method static Builder<static>|Reservation active()
 * @method static Builder<static>|Reservation eventAndUser(int $eventId, int $userId)
 * @method static \Database\Factories\ReservationFactory factory($count = null, $state = [])
 * @method static Builder<static>|Reservation newModelQuery()
 * @method static Builder<static>|Reservation newQuery()
 * @method static Builder<static>|Reservation query()
 * @method static Builder<static>|Reservation whereCanceledAt($value)
 * @method static Builder<static>|Reservation whereCreatedAt($value)
 * @method static Builder<static>|Reservation whereEventId($value)
 * @method static Builder<static>|Reservation whereExpiresAt($value)
 * @method static Builder<static>|Reservation whereId($value)
 * @method static Builder<static>|Reservation whereReservedAt($value)
 * @method static Builder<static>|Reservation whereStatus($value)
 * @method static Builder<static>|Reservation whereUpdatedAt($value)
 * @method static Builder<static>|Reservation whereUserId($value)
 * @mixin \Eloquent
 */
class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'user_id',
        'reserved_at',
        'status',
        'expires_at',
        'canceled_at',
    ];

    protected $casts = [
        'event_id' => 'integer',
        'user_id' => 'integer',
        'reserved_at' => 'datetime',
        'status' => ReservationStatus::class,
        'expires_at' => 'datetime',
        'canceled_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * 予約が確定しているかどうかを判定する
     *
     * @return bool
     */
    public function isConfirmed(): bool
    {
        return $this->status === ReservationStatus::CONFIRMED;
    }

    /**
     * 予約が保留中かどうかを判定する
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->status === ReservationStatus::PENDING;
    }

    /**
     * 予約がキャンセルされているかどうかを判定する
     *
     * @return bool
     */
    public function isCanceled(): bool
    {
        return $this->status === ReservationStatus::CANCELED;
    }

    /**
     * 有効期限切れ、またはキャンセル済みか（＝無効な予約か）
     */
    public function isInvalid(): bool
    {
        return $this->status === ReservationStatus::CANCELED ||
            $this->status === ReservationStatus::EXPIRED ||
            ($this->status === ReservationStatus::PENDING && $this->expires_at < now());
    }

    /**
     * 予約が特定のユーザーに属しているかどうかを判定する
     *
     * @param int $userId
     * @return bool
     */
    public function isOwnedBy(int $userId): bool
    {
        return $this->user_id === $userId;
    }

    /**
     * イベントIDとユーザーIDで絞り込むスコープ
     * 
     * @param Builder $query
     * @param int $eventId
     * @param int $userId
     * @return Builder
     */
    protected function scopeEventAndUser(Builder $query, int $eventId, int $userId): Builder
    {
        $query->where('event_id', $eventId)->where('user_id', $userId);
        return $query;
    }

    /**
     * アクティブな予約（確定済みまたは有効な保留中）のみを絞り込むスコープ
     * 
     * @param Builder $query
     * @return Builder
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('status', ReservationStatus::CONFIRMED)
                ->orWhere(function ($subQ) {
                    $subQ->where('status', ReservationStatus::PENDING)
                        ->where('expires_at', '>', now());
                });
        });
    }
}

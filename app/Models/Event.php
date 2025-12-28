<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{

    use HasFactory;

    protected $fillable = [
        'name',
        'total_seats',
        'start_at',
        'end_at',
    ];

    protected $casts = [
        'total_seats' => 'integer',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function isStarted(): bool
    {
        return $this->start_at !== null && $this->start_at->isPast();
    }
}

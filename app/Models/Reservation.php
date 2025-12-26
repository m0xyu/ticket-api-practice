<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{

    use HasFactory;

    protected $fillable = [
        'event_id',
        'user_id',
        'reserved_at',
    ];

    protected $casts = [
        'event_id' => 'integer',
        'user_id' => 'integer',
        'reserved_at' => 'datetime'
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prize extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_session_id',
        'juara',
        'nominal',
    ];

    public function eventSession(): BelongsTo
    {
        return $this->belongsTo(EventSession::class);
    }
}

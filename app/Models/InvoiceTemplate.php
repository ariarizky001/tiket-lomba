<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'pemisah',
        'bagian',
    ];

    protected $casts = [
        'bagian' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}

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
        'bagian_belum_lunas',
        'bagian_lunas',
    ];

    protected $casts = [
        'bagian' => 'array',
        'bagian_belum_lunas' => 'array',
        'bagian_lunas' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}

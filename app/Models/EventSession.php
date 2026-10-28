<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventSession extends Model
{
    use HasFactory;

    protected $fillable = ['event_id', 'nomor_urut', 'nama', 'harga', 'kuota'];

    protected $casts = [
        'nomor_urut' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'event_session_id');
    }

    public function prizes(): HasMany
    {
        return $this->hasMany(Prize::class, 'event_session_id');
    }

    public function scopeUrut(Builder $query): Builder
    {
        return $query->orderBy('nomor_urut', 'asc')->orderBy('id', 'asc');
    }
}

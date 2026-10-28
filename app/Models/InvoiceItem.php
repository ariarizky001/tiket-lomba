<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'event_session_id',
        'qty',
        'harga',
        'subtotal',
    ];

    protected $appends = ['session_nama', 'session_nomor'];

    protected $hidden = ['eventSession'];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(EventSession::class, 'event_session_id');
    }

    public function getSessionNamaAttribute()
    {
        return $this->session?->nama;
    }

    public function getSessionNomorAttribute()
    {
        return $this->session?->nomor_urut;
    }
}
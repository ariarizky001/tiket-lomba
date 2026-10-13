<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasFactory;

    protected $fillable = ['nama', 'tanggal', 'lokasi', 'is_active', 'invoice_judul', 'invoice_catatan'];

    protected $casts = [
        'tanggal' => 'date',
        'is_active' => 'boolean',
    ];

    public function sessions(): HasMany
    {
        return $this->hasMany(EventSession::class, 'event_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'event_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'event_id');
    }
}

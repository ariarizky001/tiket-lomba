<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LatberSession extends Model
{
    use HasFactory;

    protected $fillable = ['latber_id', 'nomor_urut', 'nama', 'harga', 'terjual', 'terpesan', 'pemasukan_terpesan'];

    protected $casts = [
        'nomor_urut' => 'integer',
        'harga' => 'integer',
        'terjual' => 'integer',
        'terpesan' => 'integer',
        'pemasukan_terpesan' => 'integer',
    ];

    protected $appends = ['pemasukan', 'pemasukan_total', 'nilai_terpesan'];

    public function latber(): BelongsTo
    {
        return $this->belongsTo(Latber::class, 'latber_id');
    }

    public function scopeUrut(Builder $query): Builder
    {
        return $query->orderBy('nomor_urut', 'asc')->orderBy('id', 'asc');
    }

    public function getPemasukanAttribute(): int
    {
        return $this->terjual * $this->harga;
    }

    public function getPemasukanTotalAttribute(): int
    {
        return ($this->terjual * $this->harga) + $this->pemasukan_terpesan;
    }

    public function getNilaiTerpesanAttribute(): int
    {
        return $this->terpesan * $this->harga;
    }
}

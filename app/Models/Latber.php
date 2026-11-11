<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Latber extends Model
{
    use HasFactory;

    protected $fillable = ['nama', 'tanggal', 'lokasi'];

    protected $casts = [
        'tanggal' => 'date',
    ];

    protected $appends = ['ringkasan'];

    public function sessions(): HasMany
    {
        return $this->hasMany(LatberSession::class, 'latber_id');
    }

    public function getRingkasanAttribute(): array
    {
        $sessions = $this->sessions()->get();
        
        return [
            'jumlah_sesi' => $sessions->count(),
            'total_terjual' => $sessions->sum('terjual'),
            'pemasukan' => $sessions->sum(function ($session) {
                return ($session->terjual * $session->harga) + $session->pemasukan_terpesan;
            }),
            'total_terpesan' => $sessions->sum('terpesan'),
            'nilai_terpesan' => $sessions->sum(function ($session) {
                return $session->terpesan * $session->harga;
            }),
            'pemasukan_terpesan' => $sessions->sum('pemasukan_terpesan'),
        ];
    }
}

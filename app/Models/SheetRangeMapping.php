<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SheetRangeMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'session_id',
        'session_index',
        'sheet_range',
        'sheet_name',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function session()
    {
        return $this->belongsTo(EventSession::class);
    }
}

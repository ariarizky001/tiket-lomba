<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventSpreadsheet extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'spreadsheet_id',
        'spreadsheet_url',
        'sheet_name',
        'credentials',
    ];

    protected $casts = [
        'credentials' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function extractSpreadsheetId(): string
    {
        if (preg_match('/\/d\/([a-zA-Z0-9-_]+)/', $this->spreadsheet_url, $matches)) {
            return $matches[1];
        }
        
        return $this->spreadsheet_id;
    }
}


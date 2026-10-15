<?php

namespace Database\Seeders;

use App\Models\Event;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        $event = Event::create([
            'nama' => '1st Anniversary Solo High Class',
            'tanggal' => Carbon::parse('2026-10-11'),
            'lokasi' => 'Solo High Class Stadium',
            'is_active' => true,
            'invoice_judul' => 'INVOICE PENDAFTARAN',
            'invoice_catatan' => 'Pembayaran dalam 3 hari kerja setelah pendaftaran',
        ]);

        $this->command->info("Event created: {$event->nama} (ID: {$event->id})");
    }
}
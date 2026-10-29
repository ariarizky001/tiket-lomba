<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventSession;
use Illuminate\Database\Seeder;

class EventSessionSeeder extends Seeder
{
    public function run(): void
    {
        $event = Event::first();

        if (!$event) {
            $this->command->error('Event not found. Please run EventSeeder first.');
            return;
        }

        $sessions = [
            ['nama' => 'MB A SAZIME', 'harga' => 550000, 'kuota' => 24],
            ['nama' => 'MB A PAKET NYAWIJI', 'harga' => 1100000, 'kuota' => 24],
            ['nama' => 'MB A PAKET SEDULURAN', 'harga' => 2200000, 'kuota' => 24],
            ['nama' => 'MB PRINSIP', 'harga' => 2200000, 'kuota' => 24],
            ['nama' => 'MB A PAKET INTEGRITAS', 'harga' => 4500000, 'kuota' => 24],
            ['nama' => 'MB B SAZIME', 'harga' => 550000, 'kuota' => 24],
            ['nama' => 'MB B PAKET NYAWIJI', 'harga' => 1100000, 'kuota' => 24],
            ['nama' => 'MB B PAKET SEDULURAN', 'harga' => 2200000, 'kuota' => 24],
            ['nama' => 'MB B PRINSIP', 'harga' => 2200000, 'kuota' => 24],
            ['nama' => 'MB B PAKET INTEGRITAS', 'harga' => 4500000, 'kuota' => 24],
            ['nama' => 'MB C SAZIME', 'harga' => 550000, 'kuota' => 24],
            ['nama' => 'MB C PAKET INTEGRITAS', 'harga' => 4500000, 'kuota' => 24],
        ];

        foreach ($sessions as $sessionData) {
            EventSession::factory()->forEvent($event)->create($sessionData);
        }

        $this->command->info("Created 12 sessions for event: {$event->nama}");
    }
}
<?php

namespace Database\Seeders;

use App\Models\Latber;
use App\Models\LatberSession;
use Illuminate\Database\Seeder;

class LatberSessionSeeder extends Seeder
{
    public function run(): void
    {
        $latber = Latber::first();

        if (!$latber) {
            $this->command->error('Latber not found. Please run LatberSeeder first.');
            return;
        }

        $sessions = [
            ['nama' => 'Murai Batu A', 'harga' => 50000],
            ['nama' => 'Murai Batu B', 'harga' => 50000],
            ['nama' => 'Cucak Hijau', 'harga' => 75000],
            ['nama' => 'Kacer', 'harga' => 60000],
            ['nama' => 'Pleci', 'harga' => 45000],
            ['nama' => 'Anis', 'harga' => 55000],
            ['nama' => 'Murai Batu C', 'harga' => 50000],
            ['nama' => 'Cucak Ijo', 'harga' => 75000],
            ['nama' => 'Kacer Jawa', 'harga' => 60000],
            ['nama' => 'Pleci Jawa', 'harga' => 45000],
            ['nama' => 'Anis Merah', 'harga' => 55000],
            ['nama' => 'Murai Batu D', 'harga' => 50000],
            ['nama' => 'Cucak Putih', 'harga' => 75000],
            ['nama' => 'Kacer Borneo', 'harga' => 60000],
            ['nama' => 'Pleci Sumatra', 'harga' => 45000],
            ['nama' => 'Anis Kelabu', 'harga' => 55000],
            ['nama' => 'Murai Batu E', 'harga' => 50000],
            ['nama' => 'Cucak Mentah', 'harga' => 75000],
        ];

        foreach ($sessions as $sessionData) {
            LatberSession::factory()->forLatber($latber)->create($sessionData);
        }

        $this->command->info("Created " . count($sessions) . " sessions for latber: {$latber->nama}");
    }
}
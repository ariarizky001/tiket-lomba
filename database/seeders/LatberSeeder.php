<?php

namespace Database\Seeders;

use App\Models\Latber;
use Illuminate\Database\Seeder;

class LatberSeeder extends Seeder
{
    public function run(): void
    {
        $latbers = [
            [
                'nama' => 'Latber Minggu',
                'tanggal' => '2026-11-15',
                'lokasi' => 'Gantangan Utama',
            ],
            [
                'nama' => 'Latber Sabtu',
                'tanggal' => '2026-11-22',
                'lokasi' => 'Lapangan Purna Karya',
            ],
        ];

        foreach ($latbers as $latberData) {
            Latber::factory()->create($latberData);
        }

        $this->command->info('Created ' . count($latbers) . ' latbers');
    }
}
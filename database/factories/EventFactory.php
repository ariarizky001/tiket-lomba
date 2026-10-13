<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventSession;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Prize;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama' => $this->faker->sentence(3),
            'tanggal' => $this->faker->date(),
            'lokasi' => $this->faker->optional()->sentence(2),
            'is_active' => true,
        ];
    }
}

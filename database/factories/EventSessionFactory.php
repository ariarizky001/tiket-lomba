<?php

namespace Database\Factories;

use App\Models\EventSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventSessionFactory extends Factory
{
    protected static ?string $nameSequence = null;

    public function definition(): array
    {
        return [
            'nama' => $this->faker->unique()->word,
            'harga' => $this->faker->numberBetween(100000, 500000),
            'kuota' => $this->faker->numberBetween(10, 100),
        ];
    }
}

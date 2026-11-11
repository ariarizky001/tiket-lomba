<?php

namespace Database\Factories;

use App\Models\Latber;
use Illuminate\Database\Eloquent\Factories\Factory;

class LatberFactory extends Factory
{
    protected $model = Latber::class;

    public function definition(): array
    {
        return [
            'nama' => $this->faker->sentence(3),
            'tanggal' => $this->faker->date(),
            'lokasi' => $this->faker->optional()->sentence(2),
        ];
    }
}
<?php

namespace Database\Factories;

use App\Models\Prize;
use Illuminate\Database\Eloquent\Factories\Factory;

class PrizeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'juara' => $this->faker->numberBetween(1, 5),
            'nominal' => $this->faker->numberBetween(100000, 1000000),
        ];
    }
}

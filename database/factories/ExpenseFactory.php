<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseFactory extends Factory
{
    public function definition(): array
    {
        $categories = config('expense.categories');

        return [
            'tanggal' => $this->faker->date(),
            'kategori' => $this->faker->randomElement($categories),
            'keterangan' => $this->faker->sentence(5),
            'penerima' => $this->faker->optional()->name(),
            'nominal' => $this->faker->numberBetween(10000, 500000),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'qty' => $this->faker->numberBetween(1, 10),
            'harga' => $this->faker->numberBetween(100000, 500000),
            'subtotal' => fn (array $attributes) => $attributes['qty'] * $attributes['harga'],
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nomor' => $this->faker->unique()->numberBetween(1, 9999),
            'kode' => $this->faker->bothify('??-####'),
            'nama_peserta' => $this->faker->name,
            'total' => $this->faker->numberBetween(100000, 1000000),
            'status' => $this->faker->randomElement(['belum_lunas', 'lunas']),
            'paid_at' => fn (array $attributes) => $attributes['status'] === 'lunas' ? $this->faker->dateTime : null,
        ];
    }

    public function withItem($session, $qty)
    {
        return $this->afterMaking(function (Invoice $invoice) use ($session, $qty) {
            InvoiceItem::factory()->count(1)->create([
                'invoice_id' => $invoice->id,
                'event_session_id' => $session->id,
                'qty' => $qty,
                'harga' => $session->harga,
                'subtotal' => $session->harga * $qty,
            ]);
        })->afterCreating(function (Invoice $invoice) use ($session, $qty) {
            InvoiceItem::factory()->count(1)->create([
                'invoice_id' => $invoice->id,
                'event_session_id' => $session->id,
                'qty' => $qty,
                'harga' => $session->harga,
                'subtotal' => $session->harga * $qty,
            ]);
        });
    }
}

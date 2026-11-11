<?php

namespace Database\Factories;

use App\Models\LatberSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class LatberSessionFactory extends Factory
{
    protected $model = LatberSession::class;

    protected static ?string $nameSequence = null;
    protected static int $nomorUrutCounter = 0;

    public function definition(): array
    {
        return [
            'nama' => $this->faker->unique()->word,
            'harga' => $this->faker->numberBetween(25000, 100000),
            'terjual' => 0,
            'terpesan' => 0,
            'pemasukan_terpesan' => 0,
            'nomor_urut' => ++self::$nomorUrutCounter,
        ];
    }

    public function forLatber($latber)
    {
        return $this->state(function (array $attributes) use ($latber) {
            $maxNomor = LatberSession::where('latber_id', $latber->id)->max('nomor_urut');
            $nextNomor = $maxNomor ? $maxNomor + 1 : 1;
            self::$nomorUrutCounter = $nextNomor;
            return [
                'latber_id' => $latber->id,
                'nomor_urut' => $nextNomor,
            ];
        });
    }
}
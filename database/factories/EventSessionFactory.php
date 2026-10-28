<?php

namespace Database\Factories;

use App\Models\EventSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventSessionFactory extends Factory
{
    protected static ?string $nameSequence = null;

    protected static int $nomorUrutCounter = 0;

    public function definition(): array
    {
        return [
            'nama' => $this->faker->unique()->word,
            'harga' => $this->faker->numberBetween(100000, 500000),
            'kuota' => $this->faker->numberBetween(10, 100),
            'nomor_urut' => ++self::$nomorUrutCounter,
        ];
    }

    public function forEvent($event)
    {
        return $this->state(function (array $attributes) use ($event) {
            $maxNomor = EventSession::where('event_id', $event->id)->max('nomor_urut');
            $nextNomor = $maxNomor ? $maxNomor + 1 : 1;
            self::$nomorUrutCounter = $nextNomor;
            return [
                'event_id' => $event->id,
                'nomor_urut' => $nextNomor,
            ];
        });
    }
}

<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\IotData>
 */
class IotDataFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mac_address' => 'MAC-SIMULATOR-' . strtoupper(Str::random(4)),
            'suhu_celcius' => $this->faker->randomFloat(2, 20, 30), // Antara 20.00 hingga 30.00
            'kelembapan_rh' => $this->faker->randomFloat(2, 50, 90), // Antara 50.00 hingga 90.00
            'created_at' => now(),
        ];
    }
}

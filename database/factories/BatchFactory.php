<?php

namespace Database\Factories;

use App\Models\Batch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Batch>
 */
class BatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'batch_id' => 'prod-' . $this->faker->unique()->numberBetween(1000, 9999),
            'batch_code' => $this->faker->unique()->bothify('BC-#####'),
            'varietas' => $this->faker->randomElement(['Arabika Gayo', 'Robusta Temanggung', 'Liberika Riau']),
            'elevation_mdpl' => $this->faker->numberBetween(800, 2000),
            'price' => $this->faker->numberBetween(10000000, 50000000),
            'status' => 'draft',
            'health_status' => 'normal',
            'exporter_id' => null,
            'description' => $this->faker->paragraph(),
            'kebun' => 'Kebun ' . $this->faker->firstName(),
            'desa' => $this->faker->streetName(),
            'kecamatan' => $this->faker->city(),
            'proses_awal' => $this->faker->randomElement(['Full Wash', 'Honey Process', 'Natural Process']),
            'status_jemur' => $this->faker->randomElement(['Belum mulai', 'Sedang berjalan', 'Selesai']),
            'metode_panen' => $this->faker->randomElement(['Petik Merah', 'Petik Campur']),
            'jumlah_karung' => $this->faker->numberBetween(5, 50),
            'tanggal_panen' => now()->subDays($this->faker->numberBetween(10, 60))->format('Y-m-d'),
            'berat_basah' => $this->faker->randomFloat(2, 250, 2500),
            'kadar_air_target' => $this->faker->randomElement(['12%', '13%', '14%']),
            'catatan' => $this->faker->sentence(),
        ];
    }
}

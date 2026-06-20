<?php

namespace Database\Factories;

use App\Models\BatchListing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BatchListing>
 */
class BatchListingFactory extends Factory
{
    protected $model = BatchListing::class;

    public function definition(): array
    {
        return [
            'id' => 'listing-' . $this->faker->unique()->numberBetween(100, 999),
            'exporter_id' => User::factory()->create(['role' => 'exporter'])->id,
            'batch_code' => 'BJI-' . strtoupper($this->faker->unique()->lexify('???')) . '-' . $this->faker->numberBetween(10000, 99999),
            'name' => 'Kopi ' . $this->faker->word(),
            'variety' => 'Arabika ' . $this->faker->word(),
            'origin' => $this->faker->city() . ', Sulawesi',
            'image_url' => 'https://storage.biji.local/listings/cover.jpg',
            'image_alt' => 'Cover Image',
            'elevation' => '1.500 mdpl',
            'harvest_date' => '2026-05-12',
            'process' => 'Penjemuran',
            'category' => 'single_origin',
            'price_per_kg' => 145000,
            'stock_kg' => 1000,
            'status' => 'listed',
            'listed_at' => now(),
        ];
    }
}

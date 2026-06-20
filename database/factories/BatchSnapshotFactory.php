<?php

namespace Database\Factories;

use App\Models\BatchSnapshot;
use App\Models\BatchListing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BatchSnapshot>
 */
class BatchSnapshotFactory extends Factory
{
    protected $model = BatchSnapshot::class;

    public function definition(): array
    {
        return [
            'batch_listing_id' => 'listing-001',
            'batch_code' => 'BJI-TRJ-26054',
            'snapshot_date' => '2026-06-03',
            'block_number' => $this->faker->numberBetween(100000, 200000),
            'transaction_hash' => '0x' . bin2hex(random_bytes(32)),
            'log_count' => $this->faker->numberBetween(20, 50),
            'avg_temperature' => $this->faker->randomFloat(2, 25, 30),
            'avg_humidity' => $this->faker->randomFloat(2, 60, 70),
            'max_temperature' => $this->faker->randomFloat(2, 30, 35),
            'min_temperature' => $this->faker->randomFloat(2, 20, 25),
            'hash' => bin2hex(random_bytes(32)),
            'is_verified' => true,
            'verified_at' => now(),
            'explorer_url' => 'https://polygonscan.com/tx/0xabc',
        ];
    }
}

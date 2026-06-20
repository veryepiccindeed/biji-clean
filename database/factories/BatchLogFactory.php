<?php

namespace Database\Factories;

use App\Models\BatchLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class BatchLogFactory extends Factory
{
    protected $model = BatchLog::class;

    public function definition(): array
    {
        return [
            'batch_id' => 'batch-001',
            'log_type' => 'drying',
            'temperature' => $this->faker->randomFloat(2, 20, 40),
            'humidity' => $this->faker->randomFloat(2, 40, 80),
            'source' => 'iot',
            'note' => $this->faker->word,
            'note_color' => $this->faker->safeColorName,
        ];
    }
}

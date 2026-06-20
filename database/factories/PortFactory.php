<?php

namespace Database\Factories;

use App\Models\Port;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Port>
 */
class PortFactory extends Factory
{
    protected $model = Port::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->city() . ' Port',
            'full_name' => 'Pelabuhan ' . $this->faker->city() . ', Indonesia',
            'country' => 'Indonesia',
            'city' => $this->faker->city(),
            'eta_days' => $this->faker->numberBetween(1, 10),
            'eta_label' => 'Estimasi 2-3 hari',
            'shipping_rate_per_kg' => 2500,
            'is_active' => true,
            'description' => $this->faker->sentence(),
        ];
    }
}

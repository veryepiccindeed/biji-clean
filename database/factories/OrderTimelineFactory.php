<?php

namespace Database\Factories;

use App\Models\OrderTimeline;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderTimeline>
 */
class OrderTimelineFactory extends Factory
{
    protected $model = OrderTimeline::class;

    public function definition(): array
    {
        return [
            'order_id' => 'ORD-1001',
            'status' => 'pending_payment',
            'is_current' => true,
            'timestamp' => now(),
            'description' => $this->faker->sentence(),
        ];
    }
}

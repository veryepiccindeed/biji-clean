<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Batch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $pricePerKg = $this->faker->numberBetween(80000, 150000);
        $weight = $this->faker->numberBetween(500, 5000);
        $subtotal = $pricePerKg * $weight;
        $shippingCost = $this->faker->numberBetween(1500000, 5000000);
        $platformFee = (int) ($subtotal * 0.01);
        $total = $subtotal + $shippingCost + $platformFee;

        return [
            'order_id' => 'order-' . $this->faker->unique()->numberBetween(1000, 9999),
            'shipping_address' => $this->faker->address(),
            'status' => 'pending_payment',
            'status_label' => 'Menunggu Pembayaran',
            'action_available' => true,
            'weight_kg' => $weight,
            'price_per_kg' => $pricePerKg,
            'subtotal' => $subtotal,
            'shipping_cost' => $shippingCost,
            'platform_fee' => $platformFee,
            'total' => $total,
            'payment_method' => $this->faker->randomElement(['bank_transfer', 'credit_card']),
            'midtrans_transaction_id' => 'MID-' . $this->faker->unique()->bothify('#####-#####'),
            'expires_at' => now()->addDay(),
            'exporter_id' => User::factory(),
            'buyer_id' => User::factory(),
            'batch_id' => Batch::factory(),
        ];
    }

    /**
     * State untuk order yang sudah dikonfirmasi
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'confirmed',
            'status_label' => 'Confirmed',
            'confirmed_at' => now(),
        ]);
    }

    /**
     * State untuk order yang selesai
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'status_label' => 'Lunas',
            'confirmed_at' => now(),
        ]);
    }

    /**
     * State untuk order yang dibatalkan
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'status_label' => 'Cancelled',
            'action_available' => false,
        ]);
    }
}

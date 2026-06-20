<?php

namespace Database\Factories;

use App\Models\BlockchainLog;
use App\Models\User;
use App\Models\Batch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlockchainLog>
 */
class BlockchainLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'log_id' => 'log-' . $this->faker->unique()->bothify('????-###'),
            'batch_id' => Batch::factory(),
            'exporter_id' => User::factory(),
            'batch_code' => function (array $attributes) {
            return Batch::find($attributes['batch_id'])->batch_id ?? 'BT-' . rand(1000, 9999);
            },
            'operation' => $this->faker->randomElement(['mint', 'transfer', 'burn', 'verify']),
            'status' => 'pending',
            'tx_hash' => '0x' . $this->faker->hexColor(),
            'error_message' => null,
            'error_type' => null,
            'label' => 'Tx #' . $this->faker->numberBetween(1000, 9999),
            'retryable' => true,
            'retry_attempt' => 0,
            'retry_scheduled_at' => null,
            'blockchain_job_id' => null,
        ];
    }

    /**
     * Status: Failed - log gagal dengan error message
     */
    public function failed(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'failed',
                'error_type' => $this->faker->randomElement(['GasEstimationFailed', 'Timeout', 'InvalidNonce', 'InsufficientBalance']),
                'error_message' => $this->faker->sentence(),
                'retryable' => true,
                'retry_attempt' => 0,
            ];
        });
    }

    /**
     * Status: Success - transaksi berhasil di-mint
     */
    public function success(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'success',
                'error_message' => null,
                'error_type' => null,
                'retryable' => false,
            ];
        });
    }

    /**
     * Status: Pending - transaksi sedang diproses
     */
    public function pending(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'pending',
                'error_message' => null,
                'error_type' => null,
                'retryable' => false,
            ];
        });
    }

    /**
     * Non-retryable log - gagal permanen
     */
    public function nonRetryable(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'failed',
                'retryable' => false,
                'error_type' => 'PermanentFailure',
                'error_message' => 'Transaction permanently failed',
            ];
        });
    }

    /**
     * Ready for retry - log dengan retryable=true
     */
    public function readyForRetry(): static
    {
        return $this->failed()->state(function (array $attributes) {
            return [
                'retryable' => true,
                'retry_attempt' => 0,
            ];
        });
    }
}

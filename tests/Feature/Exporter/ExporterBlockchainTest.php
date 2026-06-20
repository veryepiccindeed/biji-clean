<?php

namespace Tests\Feature\Exporter;

use App\Models\User;
use App\Models\BlockchainLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExporterBlockchainTest extends TestCase
{
    use RefreshDatabase;

    private User $exporter;
    private User $farmer;
    private User $buyer;
    private BlockchainLog $failureLog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exporter = User::factory()->create(['role' => 'exporter']);
        $this->farmer = User::factory()->create(['role' => 'farmer']);
        $this->buyer = User::factory()->create(['role' => 'buyer']);

        // Create blockchain failure log untuk testing
        $this->failureLog = BlockchainLog::factory()->create([
            'log_id' => 'fail-001',
            'exporter_id' => $this->exporter->id,
            'status' => 'failed',
            'error_type' => 'GasEstimationFailed',
            'retryable' => true,
        ]);
    }

    // ==================== 11.1: GET /api/v1/exporter/network/status ====================

    public function test_network_status_returns_online()
    {
        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/network/status');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
                'message' => 'Status jaringan blockchain berhasil diambil',
            ])
            ->assertJsonStructure([
                'success',
                'code',
                'message',
                'data' => [
                    'network' => [
                        'name',
                        'status',
                        'ping_ms',
                        'last_block_time',
                        'gas_price_gwei',
                        'last_checked_at',
                    ],
                ],
                'timestamp',
            ]);
    }

    public function test_network_status_has_valid_fields()
    {
        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/network/status');

        $response->assertStatus(200);
        $this->assertContains($response->json('data.network.status'), ['online', 'offline']);
        $this->assertIsNumeric($response->json('data.network.ping_ms'));
        $this->assertIsNumeric($response->json('data.network.gas_price_gwei'));
    }

    public function test_network_status_unauthorized_without_auth()
    {
        $response = $this->getJson('/api/v1/exporter/network/status');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    public function test_network_status_forbidden_with_farmer_role()
    {
        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/exporter/network/status');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }

    public function test_network_status_forbidden_with_buyer_role()
    {
        $response = $this->actingAs($this->buyer)
            ->getJson('/api/v1/exporter/network/status');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }

    // ==================== 11.2: GET /api/v1/exporter/blockchain-failure-logs ====================

    public function test_blockchain_failure_logs_returns_list_with_pagination()
    {
        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/blockchain-failure-logs');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
                'message' => 'Log kegagalan blockchain berhasil diambil',
            ])
            ->assertJsonStructure([
                'success',
                'code',
                'message',
                'data' => [],
                'pagination' => [
                    'cursor',
                    'hasMore',
                    'limit',
                ],
                'timestamp',
            ]);
    }

    public function test_blockchain_failure_logs_with_custom_limit()
    {
        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/blockchain-failure-logs?limit=50');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
            ])
            ->assertJsonPath('pagination.limit', 50);
    }

    public function test_blockchain_failure_logs_limit_capped_at_100()
    {
        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/blockchain-failure-logs?limit=150');

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(100, $response->json('pagination.limit'));
    }

    public function test_blockchain_failure_logs_with_cursor_pagination()
    {
        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/blockchain-failure-logs?cursor=eyJpZCI6ImZhaWwtMDAyIn0=');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
            ]);
    }

    public function test_blockchain_failure_logs_contains_required_fields()
    {
        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/blockchain-failure-logs?limit=10');

        $response->assertStatus(200);

        // Jika ada logs, check structure
        $data = $response->json('data');
        if (!empty($data)) {
            $this->assertArrayHasKey('id', $data[0]);
            $this->assertArrayHasKey('label', $data[0]);
            $this->assertArrayHasKey('batch_code', $data[0]);
            $this->assertArrayHasKey('error_type', $data[0]);
            $this->assertArrayHasKey('error_message', $data[0]);
            $this->assertArrayHasKey('timestamp', $data[0]);
            $this->assertArrayHasKey('retryable', $data[0]);
            $this->assertArrayHasKey('retry_url', $data[0]);
        }
    }

    public function test_blockchain_failure_logs_unauthorized_without_auth()
    {
        $response = $this->getJson('/api/v1/exporter/blockchain-failure-logs');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    public function test_blockchain_failure_logs_forbidden_with_farmer_role()
    {
        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/exporter/blockchain-failure-logs');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }

    public function test_blockchain_failure_logs_forbidden_with_buyer_role()
    {
        $response = $this->actingAs($this->buyer)
            ->getJson('/api/v1/exporter/blockchain-failure-logs');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }

    public function test_blockchain_failure_logs_data_isolation()
    {
        // Create another exporter with their own log
        $exporter2 = User::factory()->create(['role' => 'exporter']);
        BlockchainLog::factory()->create([
            'log_id' => 'fail-002',
            'exporter_id' => $exporter2->id,
            'status' => 'failed',
        ]);

        // First exporter should only see their own logs
        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/blockchain-failure-logs');

        $response->assertStatus(200);

        // Verify data isolation - should not see exporter2's logs
        $data = $response->json('data');
        foreach ($data as $log) {
            $this->assertNotEquals('fail-002', $log['id']);
        }
    }

    // ==================== 11.3: POST /api/v1/exporter/blockchain-logs/{logId}/retry ====================

    public function test_retry_blockchain_log_returns_accepted()
    {
        $response = $this->actingAs($this->exporter)
            ->postJson('/api/v1/exporter/blockchain-logs/fail-001/retry', []);

        $response->assertStatus(202)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
                'message' => 'Transaksi dijadwalkan ulang',
            ])
            ->assertJsonStructure([
                'success',
                'code',
                'message',
                'data' => [
                    'log' => [
                        'id',
                        'batch_code',
                        'status',
                        'retry_attempt',
                        'retry_scheduled_at',
                        'blockchain_job_id',
                    ],
                ],
                'timestamp',
            ]);
    }

    public function test_retry_blockchain_log_increments_retry_count()
    {
        $response = $this->actingAs($this->exporter)
            ->postJson('/api/v1/exporter/blockchain-logs/fail-001/retry', []);

        $response->assertStatus(202);

        // Verify retry_attempt increased
        $retryAttempt = $response->json('data.log.retry_attempt');
        $this->assertGreaterThanOrEqual(1, $retryAttempt);
    }

    public function test_retry_blockchain_log_not_found()
    {
        $response = $this->actingAs($this->exporter)
            ->postJson('/api/v1/exporter/blockchain-logs/invalid-log-id/retry', []);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code' => 'NOT_FOUND',
            ]);
    }

    public function test_retry_blockchain_log_not_retryable()
    {
        // Create non-retryable log
        $nonRetryableLog = BlockchainLog::factory()->create([
            'log_id' => 'fail-non-retryable',
            'exporter_id' => $this->exporter->id,
            'status' => 'failed',
            'retryable' => false,
        ]);

        $response = $this->actingAs($this->exporter)
            ->postJson('/api/v1/exporter/blockchain-logs/fail-non-retryable/retry', []);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'code' => 'INVALID_STATUS_TRANSITION',
            ]);
    }

    public function test_retry_blockchain_log_already_pending()
    {
        // Create pending log
        $pendingLog = BlockchainLog::factory()->create([
            'log_id' => 'fail-pending',
            'exporter_id' => $this->exporter->id,
            'status' => 'pending',
            'retryable' => true,
        ]);

        $response = $this->actingAs($this->exporter)
            ->postJson('/api/v1/exporter/blockchain-logs/fail-pending/retry', []);

        // Bisa 409 (conflict - already retrying) atau 400
        $this->assertThat(
            $response->status(),
            $this->logicalOr(
                $this->equalTo(400),
                $this->equalTo(409)
            )
        );
    }

    public function test_retry_blockchain_log_data_isolation_forbidden()
    {
        // Create another exporter
        $exporter2 = User::factory()->create(['role' => 'exporter']);

        $response = $this->actingAs($exporter2)
            ->postJson('/api/v1/exporter/blockchain-logs/fail-001/retry', []);

        // Should get 403 or 404 (data isolation)
        $this->assertThat(
            $response->status(),
            $this->logicalOr(
                $this->equalTo(403),
                $this->equalTo(404)
            )
        );
    }

    public function test_retry_blockchain_log_unauthorized_without_auth()
    {
        $response = $this->postJson('/api/v1/exporter/blockchain-logs/fail-001/retry', []);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    public function test_retry_blockchain_log_forbidden_with_farmer_role()
    {
        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/exporter/blockchain-logs/fail-001/retry', []);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }

    public function test_retry_blockchain_log_forbidden_with_buyer_role()
    {
        $response = $this->actingAs($this->buyer)
            ->postJson('/api/v1/exporter/blockchain-logs/fail-001/retry', []);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }
}

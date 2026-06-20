<?php

namespace Tests\Feature\Exporter;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardExporterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper: Create exporter user
     */
    private function createExporter(): User
    {
        return User::factory()->create(['role' => 'exporter']);
    }

    /**
     * Helper: Create farmer user
     */
    private function createFarmer(): User
    {
        return User::factory()->create(['role' => 'farmer']);
    }

    // ============================================================================
    // Section 8.1 - GET /api/v1/exporter/dashboard
    // ============================================================================

    /**
     * Test exporter dashboard success
     * GET /api/v1/exporter/dashboard - Happy path
     * API_CONTRACT 8.1
     */
    public function test_exporter_dashboard_success(): void
    {
        $exporter = $this->createExporter();
        $token = $exporter->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/exporter/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'code',
                'message',
                'data' => [
                    'stats' => [
                        'total_batches_acquired',
                        'total_batches_caption',
                        'certificates_issued',
                        'certificates_growth_percent',
                        'certificates_growth_label',
                        'certificates_growth_period',
                        'pending_actions_count',
                        'pending_actions_detail',
                        'batches_ready_for_acquisition',
                        'batches_ready_caption',
                        'revenue_total',
                    ],
                    'network_status' => [
                        'name',
                        'status',
                        'ping_ms',
                        'last_checked_at',
                    ],
                    'blockchain_failure_logs' => [
                        '*' => [
                            'id',
                            'label',
                            'error_type',
                            'timestamp',
                            'retryable',
                        ],
                    ],
                    'latest_batches' => [
                        '*' => [
                            'id',
                            'variety',
                            'farmer_name',
                            'elevation_mdpl',
                            'status',
                            'health_status',
                            'action_label',
                            'action_available',
                        ],
                    ],
                    'recent_orders' => [
                        '*' => [
                            'id',
                            'order_number',
                            'buyer_name',
                            'batch_code',
                            'amount',
                            'status',
                            'status_label',
                            'action_available',
                            'created_at',
                        ],
                    ],
                ],
                'timestamp',
            ])
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
            ]);
    }

    /**
     * Test exporter dashboard fails - Not authenticated
     * GET /api/v1/exporter/dashboard - Unauthorized
     * API_CONTRACT 8.1
     */
    public function test_exporter_dashboard_fails_without_authentication(): void
    {
        $response = $this->getJson('/api/v1/exporter/dashboard');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    /**
     * Test exporter dashboard fails - Not exporter role
     * GET /api/v1/exporter/dashboard - Forbidden
     * API_CONTRACT 8.1 (RBAC)
     */
    public function test_exporter_dashboard_fails_with_farmer_role(): void
    {
        $farmer = $this->createFarmer();

        $response = $this->actingAs($farmer, 'sanctum')
            ->getJson('/api/v1/exporter/dashboard');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }

    // ============================================================================
    // Section 8.2 - GET /api/v1/exporter/blockchain-activity
    // ============================================================================

    /**
     * Test blockchain activity with 3month range
     * GET /api/v1/exporter/blockchain-activity - Happy path (default range)
     * API_CONTRACT 8.2
     */
    public function test_blockchain_activity_success_with_3month_range(): void
    {
        $exporter = $this->createExporter();
        $token = $exporter->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/exporter/blockchain-activity?range=3month');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'code',
                'message',
                'data' => [
                    'range',
                    'period' => [
                        'start_date',
                        'end_date',
                    ],
                    'chart_data' => [
                        '*' => [
                            'date',
                            'acquisitions',
                            'certifications',
                        ],
                    ],
                ],
                'timestamp',
            ])
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
                'data' => [
                    'range' => '3month',
                ],
            ]);
    }

    /**
     * Test blockchain activity with 6month range
     * GET /api/v1/exporter/blockchain-activity - Different range parameter
     * API_CONTRACT 8.2
     */
    public function test_blockchain_activity_success_with_6month_range(): void
    {
        $exporter = $this->createExporter();
        $token = $exporter->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/exporter/blockchain-activity?range=6month');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
                'data' => [
                    'range' => '6month',
                ],
            ]);
    }

    /**
     * Test blockchain activity with custom date range
     * GET /api/v1/exporter/blockchain-activity - Custom range
     * API_CONTRACT 8.2
     */
    public function test_blockchain_activity_success_with_custom_range(): void
    {
        $exporter = $this->createExporter();
        $token = $exporter->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/exporter/blockchain-activity?range=custom&startDate=2026-04-01&endDate=2026-05-23');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
                'data' => [
                    'range' => 'custom',
                ],
            ]);
    }

    /**
     * Test blockchain activity fails - Missing required date parameters
     * GET /api/v1/exporter/blockchain-activity - Validation error (range=custom without dates)
     * API_CONTRACT 8.2
     */
    public function test_blockchain_activity_fails_with_custom_range_missing_dates(): void
    {
        $exporter = $this->createExporter();
        $token = $exporter->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/exporter/blockchain-activity?range=custom');

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    /**
     * Test blockchain activity fails - Invalid date format
     * GET /api/v1/exporter/blockchain-activity - Validation error
     * API_CONTRACT 8.2
     */
    public function test_blockchain_activity_fails_with_invalid_date_format(): void
    {
        $exporter = $this->createExporter();
        $token = $exporter->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/exporter/blockchain-activity?range=custom&startDate=invalid&endDate=2026-05-23');

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    /**
     * Test blockchain activity fails - Not authenticated
     * GET /api/v1/exporter/blockchain-activity - Unauthorized
     * API_CONTRACT 8.2 (RBAC)
     */
    public function test_blockchain_activity_fails_without_authentication(): void
    {
        $response = $this->getJson('/api/v1/exporter/blockchain-activity?range=3month');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    /**
     * Test blockchain activity fails - Not exporter role
     * GET /api/v1/exporter/blockchain-activity - Forbidden
     * API_CONTRACT 8.2 (RBAC)
     */
    public function test_blockchain_activity_fails_with_farmer_role(): void
    {
        $farmer = $this->createFarmer();

        $response = $this->actingAs($farmer, 'sanctum')
            ->getJson('/api/v1/exporter/blockchain-activity?range=3month');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }

    // ============================================================================
    // Section 8.3 - GET /api/v1/exporter/blockchain-logs
    // ============================================================================

    /**
     * Test blockchain logs success with default limit
     * GET /api/v1/exporter/blockchain-logs - Happy path
     * API_CONTRACT 8.3
     */
    public function test_blockchain_logs_success_with_default_limit(): void
    {
        $exporter = $this->createExporter();
        $token = $exporter->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/exporter/blockchain-logs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'code',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'batch_code',
                        'operation',
                        'status',
                        'tx_hash',
                        'created_at',
                    ],
                ],
                'pagination' => [
                    'cursor',
                    'hasMore',
                    'limit',
                ],
                'timestamp',
            ])
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
                'pagination' => [
                    'limit' => 20,
                ],
            ]);
    }

    /**
     * Test blockchain logs with custom limit
     * GET /api/v1/exporter/blockchain-logs - Custom limit parameter
     * API_CONTRACT 8.3
     */
    public function test_blockchain_logs_success_with_custom_limit(): void
    {
        $exporter = $this->createExporter();
        $token = $exporter->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/exporter/blockchain-logs?limit=50');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
                'pagination' => [
                    'limit' => 50,
                ],
            ]);
    }

    /**
     * Test blockchain logs respects max limit
     * GET /api/v1/exporter/blockchain-logs - Max limit is 100
     * API_CONTRACT 8.3
     */
    public function test_blockchain_logs_respects_max_limit_of_100(): void
    {
        $exporter = $this->createExporter();
        $token = $exporter->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/exporter/blockchain-logs?limit=500');

        $response->assertStatus(200);
        
        // Limit should be capped at 100
        $limit = $response->json('pagination.limit');
        $this->assertLessThanOrEqual(100, $limit);
    }

    /**
     * Test blockchain logs with status filter - success
     * GET /api/v1/exporter/blockchain-logs - Filter by status
     * API_CONTRACT 8.3
     */
    public function test_blockchain_logs_success_with_status_filter_success(): void
    {
        $exporter = $this->createExporter();
        $token = $exporter->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/exporter/blockchain-logs?status=success');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
            ]);
    }

    /**
     * Test blockchain logs with status filter - pending
     * GET /api/v1/exporter/blockchain-logs - Filter by status
     * API_CONTRACT 8.3
     */
    public function test_blockchain_logs_success_with_status_filter_pending(): void
    {
        $exporter = $this->createExporter();
        $token = $exporter->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/exporter/blockchain-logs?status=pending');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
            ]);
    }

    /**
     * Test blockchain logs with status filter - failed
     * GET /api/v1/exporter/blockchain-logs - Filter by status
     * API_CONTRACT 8.3
     */
    public function test_blockchain_logs_success_with_status_filter_failed(): void
    {
        $exporter = $this->createExporter();
        $token = $exporter->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/exporter/blockchain-logs?status=failed');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
            ]);
    }

    /**
     * Test blockchain logs with cursor pagination
     * GET /api/v1/exporter/blockchain-logs - Cursor-based pagination
     * API_CONTRACT 8.3
     */
    public function test_blockchain_logs_success_with_cursor_pagination(): void
    {
        $exporter = $this->createExporter();
        $token = $exporter->createToken('auth_token')->plainTextToken;

        // First request
        $response1 = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/exporter/blockchain-logs?limit=10');

        $response1->assertStatus(200);

        // If hasMore is true, next request should use cursor from response
        if ($response1->json('pagination.hasMore')) {
            $cursor = $response1->json('pagination.cursor');
            $this->assertNotNull($cursor);

            $response2 = $this->withHeader('Authorization', "Bearer {$token}")
                ->getJson("/api/v1/exporter/blockchain-logs?limit=10&cursor={$cursor}");

            $response2->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'code' => 'SUCCESS',
                ]);
        }
    }

    /**
     * Test blockchain logs fails - Not authenticated
     * GET /api/v1/exporter/blockchain-logs - Unauthorized
     * API_CONTRACT 8.3 (RBAC)
     */
    public function test_blockchain_logs_fails_without_authentication(): void
    {
        $response = $this->getJson('/api/v1/exporter/blockchain-logs');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    /**
     * Test blockchain logs fails - Not exporter role
     * GET /api/v1/exporter/blockchain-logs - Forbidden
     * API_CONTRACT 8.3 (RBAC)
     */
    public function test_blockchain_logs_fails_with_farmer_role(): void
    {
        $farmer = $this->createFarmer();

        $response = $this->actingAs($farmer, 'sanctum')
            ->getJson('/api/v1/exporter/blockchain-logs');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }

    /**
     * Test blockchain logs fails - Invalid limit (non-integer)
     * GET /api/v1/exporter/blockchain-logs - Validation error
     * API_CONTRACT 8.3
     */
    public function test_blockchain_logs_fails_with_invalid_limit(): void
    {
        $exporter = $this->createExporter();
        $token = $exporter->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/exporter/blockchain-logs?limit=abc');

        // Should either return 422 or cast to default/safe value
        $this->assertThat(
            $response->getStatusCode(),
            $this->logicalOr(
                $this->equalTo(422),
                $this->equalTo(200)
            )
        );
    }

    /**
     * Test blockchain logs fails - Invalid status filter
     * GET /api/v1/exporter/blockchain-logs - Validation error
     * API_CONTRACT 8.3
     */
    public function test_blockchain_logs_fails_with_invalid_status_filter(): void
    {
        $exporter = $this->createExporter();
        $token = $exporter->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/exporter/blockchain-logs?status=invalid_status');

        // Should return 422 for invalid status
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    /**
     * Test blockchain logs response includes error_message for failed transactions
     * GET /api/v1/exporter/blockchain-logs - Response structure for failed logs
     * API_CONTRACT 8.3
     */
    public function test_blockchain_logs_includes_error_message_for_failed_transactions(): void
    {
        $exporter = $this->createExporter();
        $token = $exporter->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/exporter/blockchain-logs?status=failed');

        $response->assertStatus(200);

        // If there are failed logs, they should have error_message field
        $failedLogs = $response->json('data');
        if (!empty($failedLogs)) {
            foreach ($failedLogs as $log) {
                $this->assertEquals('failed', $log['status']);
                // error_message should be present for failed logs
                $this->assertArrayHasKey('error_message', $log);
            }
        }
    }
}

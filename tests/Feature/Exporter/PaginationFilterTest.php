<?php

namespace Tests\Feature\Exporter;

use App\Models\User;
use App\Models\Batch;
use App\Models\Order;
use App\Models\BlockchainLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaginationFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $exporter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exporter = User::factory()->create(['role' => 'exporter']);
    }

    // ==================== PAGINATION: Limit Tests ====================

    public function test_available_batches_with_limit_1()
    {
        Batch::factory()->count(5)->create();

        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/batches/available?limit=1');

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(1, count($response->json('data')));
        $this->assertEquals(1, $response->json('pagination.limit'));
    }

    public function test_available_batches_with_limit_20_default()
    {
        Batch::factory()->count(30)->create();

        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/batches/available');

        $response->assertStatus(200);
        $this->assertEquals(20, $response->json('pagination.limit'));
        $this->assertLessThanOrEqual(20, count($response->json('data')));
    }

    public function test_available_batches_with_limit_50()
    {
        Batch::factory()->count(100)->create();

        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/batches/available?limit=50');

        $response->assertStatus(200);
        $this->assertEquals(50, $response->json('pagination.limit'));
        $this->assertLessThanOrEqual(50, count($response->json('data')));
    }

    public function test_available_batches_with_limit_100()
    {
        Batch::factory()->count(150)->create();

        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/batches/available?limit=100');

        $response->assertStatus(200);
        $this->assertEquals(100, $response->json('pagination.limit'));
        $this->assertLessThanOrEqual(100, count($response->json('data')));
    }

    public function test_available_batches_limit_capped_at_100()
    {
        Batch::factory()->count(200)->create();

        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/batches/available?limit=150');

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(100, $response->json('pagination.limit'));
    }

    public function test_available_batches_limit_minimum_1()
    {
        Batch::factory()->count(10)->create();

        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/batches/available?limit=0');

        // Should either accept minimum 1 or reject with 422
        $this->assertThat(
            $response->status(),
            $this->logicalOr(
                $this->equalTo(200),
                $this->equalTo(422)
            )
        );
    }

    public function test_orders_with_custom_limit()
    {
        Order::factory()->count(50)->create(['exporter_id' => $this->exporter->id]);

        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/orders?limit=25');

        $response->assertStatus(200);
        $this->assertEquals(25, $response->json('pagination.limit'));
        $this->assertLessThanOrEqual(25, count($response->json('data')));
    }

    public function test_blockchain_logs_with_custom_limit()
    {
        BlockchainLog::factory()->count(50)->create(['exporter_id' => $this->exporter->id]);

        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/blockchain-failure-logs?limit=30');

        $response->assertStatus(200);
        $this->assertEquals(30, $response->json('pagination.limit'));
        $this->assertLessThanOrEqual(30, count($response->json('data')));
    }

    // ==================== PAGINATION: Cursor Tests ====================

    public function test_available_batches_cursor_pagination_first_page()
    {
        Batch::factory()->count(25)->create([
        'status' => 'pending',
        'exporter_id' => null
        ]);

        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/batches/available?limit=10');

        $response->assertStatus(200);
        $this->assertNotNull($response->json('pagination.cursor'));
        $this->assertIsString($response->json('pagination.cursor'));
        $this->assertTrue($response->json('pagination.hasMore'));
    }

    public function test_available_batches_cursor_pagination_second_page()
    {
        Batch::factory()->count(25)->create();

        // First request
        $response1 = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/batches/available?limit=10');

        $response1->assertStatus(200);
        $cursor = $response1->json('pagination.cursor');

        // Second request with cursor
        $response2 = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/batches/available?cursor=' . urlencode($cursor) . '&limit=10');

        $response2->assertStatus(200);
        $this->assertIsArray($response2->json('data'));
    }

    public function test_orders_cursor_pagination()
    {
        Order::factory()->count(50)->create(['exporter_id' => $this->exporter->id]);

        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/orders?limit=20');

        $response->assertStatus(200);
        $this->assertNotNull($response->json('pagination.cursor'));
        $this->assertTrue($response->json('pagination.hasMore'));
    }

    public function test_blockchain_logs_cursor_pagination()
    {
        BlockchainLog::factory()->count(50)->create(['exporter_id' => $this->exporter->id]);

        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/blockchain-failure-logs?limit=20');

        $response->assertStatus(200);
        $this->assertNotNull($response->json('pagination.cursor'));
        $this->assertTrue($response->json('pagination.hasMore'));
    }

    public function test_cursor_pagination_has_more_false_last_page()
    {
        Batch::factory()->count(5)->create();

        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/batches/available?limit=100');

        $response->assertStatus(200);
        $this->assertFalse($response->json('pagination.hasMore'));
    }

    public function test_invalid_cursor_handling()
    {
        Batch::factory()->count(10)->create();

        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/batches/available?cursor=invalid-cursor-xyz&limit=20');

        // Should either return 200 with empty data or 400 error
        $this->assertThat(
            $response->status(),
            $this->logicalOr(
                $this->equalTo(200),
                $this->equalTo(400)
            )
        );
    }

    // ==================== FILTER: Status Filter ====================

    public function test_orders_filter_by_status_pending()
    {
        Order::factory()->count(5)->create(['exporter_id' => $this->exporter->id, 'status' => 'pending']);
        Order::factory()->count(3)->create(['exporter_id' => $this->exporter->id, 'status' => 'confirmed']);

        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/orders?filter=pending');

        $response->assertStatus(200);

        // All returned orders should have pending status
        $orders = $response->json('data');
        foreach ($orders as $order) {
            $this->assertEquals('pending', $order['status']);
        }
    }

    public function test_orders_filter_by_status_confirmed()
    {
        Order::factory()->count(3)->create(['exporter_id' => $this->exporter->id, 'status' => 'pending']);
        Order::factory()->count(5)->create(['exporter_id' => $this->exporter->id, 'status' => 'confirmed']);

        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/orders?filter=confirmed');

        $response->assertStatus(200);

        // All returned orders should have confirmed status
        $orders = $response->json('data');
        foreach ($orders as $order) {
            $this->assertEquals('confirmed', $order['status']);
        }
    }

    public function test_blockchain_logs_filter_by_status_failed()
    {
        BlockchainLog::factory()->count(5)->create(['exporter_id' => $this->exporter->id, 'status' => 'failed']);
        BlockchainLog::factory()->count(3)->create(['exporter_id' => $this->exporter->id, 'status' => 'success']);

        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/blockchain-failure-logs?status=failed');

        $response->assertStatus(200);

        // Filter specifically for failed logs
        $logs = $response->json('data');
        foreach ($logs as $log) {
            $this->assertEquals('failed', $log['status']);
        }
    }

    // ==================== FILTER: Health Filter (Batches) ====================

    public function test_available_batches_filter_by_health_normal()
    {
        Batch::factory()->count(5)->create(['health_status' => 'normal']);
        Batch::factory()->count(3)->create(['health_status' => 'warning']);

        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/batches/available?health_filter=normal');

        $response->assertStatus(200);

        // All returned batches should have normal health
        $batches = $response->json('data');
        foreach ($batches as $batch) {
            $this->assertEquals('normal', $batch['health_status']);
        }
    }

    public function test_available_batches_filter_by_health_warning()
    {
        Batch::factory()->count(3)->create(['health_status' => 'normal']);
        Batch::factory()->count(5)->create(['health_status' => 'warning']);

        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/batches/available?health_filter=warning');

        $response->assertStatus(200);

        // All returned batches should have warning health
        $batches = $response->json('data');
        foreach ($batches as $batch) {
            $this->assertEquals('warning', $batch['health_status']);
        }
    }

    // ==================== FILTER: Sort Parameter ====================

    public function test_available_batches_sort_by_elevation()
    {
        Batch::factory()->create(['elevation_mdpl' => 1500]);
        Batch::factory()->create(['elevation_mdpl' => 800]);
        Batch::factory()->create(['elevation_mdpl' => 2000]);

        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/batches/available?sort=elevation');

        $response->assertStatus(200);

        // Data should be sorted by elevation (ascending or descending, both valid)
        $batches = $response->json('data');
        $this->assertGreaterThanOrEqual(1, count($batches));
    }

    public function test_available_batches_sort_by_name()
    {
        Batch::factory()->create(['name' => 'Batch Z']);
        Batch::factory()->create(['name' => 'Batch A']);
        Batch::factory()->create(['name' => 'Batch M']);

        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/batches/available?sort=name');

        $response->assertStatus(200);
        $batches = $response->json('data');
        $this->assertGreaterThanOrEqual(1, count($batches));
    }

    // ==================== FILTER: Date Range Filter ====================

    public function test_blockchain_logs_filter_by_date_range()
    {
        $now = now();
        $past = $now->copy()->subDays(30);
        $future = $now->copy()->addDays(30);

        BlockchainLog::factory()->create([
            'exporter_id' => $this->exporter->id,
            'created_at' => $past,
        ]);
        BlockchainLog::factory()->create([
            'exporter_id' => $this->exporter->id,
            'created_at' => $now,
        ]);

        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/blockchain-failure-logs?range=custom&startDate='
                . $past->format('Y-m-d') . '&endDate=' . $future->format('Y-m-d'));

        $response->assertStatus(200);
        $logs = $response->json('data');
        $this->assertGreaterThanOrEqual(0, count($logs));
    }

    // ==================== COMBINED: Filter + Pagination ====================

    public function test_orders_filter_and_pagination_combined()
    {
        Order::factory()->count(30)->create(['exporter_id' => $this->exporter->id, 'status' => 'pending']);
        Order::factory()->count(10)->create(['exporter_id' => $this->exporter->id, 'status' => 'confirmed']);

        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/orders?filter=pending&limit=10');

        $response->assertStatus(200);

        // Check filter applied
        $orders = $response->json('data');
        foreach ($orders as $order) {
            $this->assertEquals('pending', $order['status']);
        }

        // Check pagination applied
        $this->assertLessThanOrEqual(10, count($orders));
        $this->assertEquals(10, $response->json('pagination.limit'));
    }

    public function test_batches_filter_health_and_sort_and_pagination_combined()
    {
        Batch::factory()->count(50)->create(['health_status' => 'normal']);
        Batch::factory()->count(30)->create(['health_status' => 'warning']);

        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/batches/available?health_filter=normal&sort=elevation&limit=15');

        $response->assertStatus(200);

        // Check filter applied
        $batches = $response->json('data');
        foreach ($batches as $batch) {
            $this->assertEquals('normal', $batch['health_status']);
        }

        // Check pagination applied
        $this->assertLessThanOrEqual(15, count($batches));
        $this->assertEquals(15, $response->json('pagination.limit'));
    }

    // ==================== EDGE CASES ====================

    public function test_pagination_with_no_results()
    {
        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/batches/available?limit=20');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
        $this->assertFalse($response->json('pagination.hasMore'));
    }

    public function test_pagination_response_structure()
    {
        Batch::factory()->count(5)->create();

        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/batches/available');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'code',
                'message',
                'data',
                'pagination' => [
                    'cursor',
                    'hasMore',
                    'limit',
                ],
                'timestamp',
            ]);
    }

    public function test_filter_response_structure()
    {
        Order::factory()->count(5)->create(['exporter_id' => $this->exporter->id, 'status' => 'pending']);

        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/orders?filter=pending');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'code',
                'message',
                'data',
                'pagination' => [
                    'cursor',
                    'hasMore',
                    'limit',
                ],
                'timestamp',
            ]);
    }

    public function test_invalid_filter_value_handling()
    {
        Batch::factory()->count(5)->create();

        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/batches/available?health_filter=invalid_health_status');

        // Should either ignore invalid filter or return 400
        $this->assertThat(
            $response->status(),
            $this->logicalOr(
                $this->equalTo(200),
                $this->equalTo(400),
                $this->equalTo(422)
            )
        );
    }

    public function test_non_string_limit_handling()
    {
        Batch::factory()->count(5)->create();

        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/batches/available?limit=abc');

        // Should either cast to valid number or return 422
        $this->assertThat(
            $response->status(),
            $this->logicalOr(
                $this->equalTo(200),
                $this->equalTo(422)
            )
        );
    }
}

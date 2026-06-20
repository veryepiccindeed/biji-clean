<?php

namespace Tests\Feature\Exporter;

use App\Models\User;
use App\Models\Order;
use App\Models\Batch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExporterOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $exporter;
    private User $farmer;
    private User $buyer;
    private Batch $batch;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exporter = User::factory()->create(['role' => 'exporter']);
        $this->farmer = User::factory()->create(['role' => 'farmer']);
        $this->buyer = User::factory()->create(['role' => 'buyer']);

        // Create batch untuk testing
        $this->batch = Batch::factory()->create([
            'batch_id' => 'cert-001',
            'exporter_id' => $this->exporter->id,
            'status' => 'released',
        ]);

        // Create order untuk testing
        $this->order = Order::factory()->create([
            'order_id' => 'order-001',
            'batch_id' => $this->batch->id,
            'exporter_id' => $this->exporter->id,
            'buyer_id' => $this->buyer->id,
            'status' => 'pending',
        ]);
    }

    // ==================== 10.1: GET /api/v1/exporter/orders ====================

    public function test_orders_returns_list_with_pagination()
    {
       // $this->withoutExceptionHandling();

        $response = $this->actingAs($this->exporter, 'sanctum')
            ->getJson('/api/v1/exporter/orders');

        $response->dump();

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
                'message' => 'Daftar pesanan berhasil diambil',
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

    public function test_orders_with_custom_limit()
    {
        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/orders?limit=50');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
            ])
            ->assertJsonPath('pagination.limit', 50);
    }

    public function test_orders_limit_capped_at_100()
    {
        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/orders?limit=150');

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(100, $response->json('pagination.limit'));
    }

    public function test_orders_with_filter_pending()
    {
        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/orders?filter=pending');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
            ]);
    }

    public function test_orders_with_filter_confirmed()
    {
        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/orders?filter=confirmed');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
            ]);
    }

    public function test_orders_with_filter_completed()
    {
        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/orders?filter=completed');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
            ]);
    }

    public function test_orders_with_filter_cancelled()
    {
        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/orders?filter=cancelled');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
            ]);
    }

    public function test_orders_with_cursor_pagination()
    {
        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/orders?cursor=eyJpZCI6Im9yZGVyLTAwMyJ9');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
            ]);
    }

    public function test_orders_unauthorized_without_auth()
    {
        $response = $this->getJson('/api/v1/exporter/orders');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    public function test_orders_forbidden_with_farmer_role()
    {
        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/exporter/orders');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }

    public function test_orders_forbidden_with_buyer_role()
    {
        $response = $this->actingAs($this->buyer)
            ->getJson('/api/v1/exporter/orders');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }

    // ==================== 10.2: GET /api/v1/exporter/orders/{orderId} ====================

    public function test_show_order_returns_detail()
    {
        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/orders/order-001');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
                'message' => 'Detail pesanan berhasil diambil',
            ])
            ->assertJsonStructure([
                'success',
                'code',
                'message',
                'data' => [
                    'order' => [
                        'id',
                        'order_number',
                        'certificate_id',
                        'batch_code',
                        'batch' => [
                            'variety',
                            'elevation',
                        ],
                        'buyer' => [
                            'id',
                            'name',
                            'email',
                            'contact',
                        ],
                        'amount',
                        'currency',
                        'status',
                        'status_timeline',
                        'payment_confirmed_at',
                        'completed_at',
                        'shipping_address',
                        'created_at',
                    ],
                ],
                'timestamp',
            ]);
    }

    public function test_show_order_not_found()
    {
        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/orders/invalid-order-id');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code' => 'NOT_FOUND',
            ]);
    }

    public function test_show_order_data_isolation_forbidden()
    {
        // Create another exporter
        $exporter2 = User::factory()->create(['role' => 'exporter']);

        $response = $this->actingAs($exporter2)
            ->getJson('/api/v1/exporter/orders/order-001');

        // Should get 403 or 404 (data isolation)
        $this->assertThat(
            $response->status(),
            $this->logicalOr(
                $this->equalTo(403),
                $this->equalTo(404)
            )
        );
    }

    public function test_show_order_unauthorized_without_auth()
    {
        $response = $this->getJson('/api/v1/exporter/orders/order-001');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    public function test_show_order_forbidden_with_farmer_role()
    {
        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/exporter/orders/order-001');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }

    public function test_show_order_forbidden_with_buyer_role()
    {
        $response = $this->actingAs($this->buyer)
            ->getJson('/api/v1/exporter/orders/order-001');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }

    // ==================== 10.3: POST /api/v1/exporter/orders/{orderId}/confirm ====================

    public function test_confirm_order_returns_success()
    {
        $order = Order::factory()->create([
            'order_id' => 'order-002',
            'batch_id' => $this->batch->id,
            'exporter_id' => $this->exporter->id,
            'buyer_id' => $this->buyer->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->exporter)
            ->postJson('/api/v1/exporter/orders/order-002/confirm', []);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
                'message' => 'Pesanan berhasil dikonfirmasi',
            ])
            ->assertJsonStructure([
                'success',
                'code',
                'message',
                'data' => [
                    'order' => [
                        'id',
                        'order_number',
                        'status',
                        'confirmed_at',
                    ],
                ],
                'timestamp',
            ]);
    }

    public function test_confirm_order_already_confirmed()
    {
        $order = Order::factory()->create([
            'order_id' => 'order-003',
            'batch_id' => $this->batch->id,
            'exporter_id' => $this->exporter->id,
            'buyer_id' => $this->buyer->id,
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        $response = $this->actingAs($this->exporter)
            ->postJson('/api/v1/exporter/orders/order-003/confirm', []);

        // Bisa status 200 (idempotent) atau 409 (sudah confirmed)
        $this->assertThat(
            $response->status(),
            $this->logicalOr(
                $this->equalTo(200),
                $this->equalTo(409)
            )
        );
    }

    public function test_confirm_order_invalid_status_bad_request()
    {
        $order = Order::factory()->create([
            'order_id' => 'order-004',
            'batch_id' => $this->batch->id,
            'exporter_id' => $this->exporter->id,
            'buyer_id' => $this->buyer->id,
            'status' => 'cancelled',
        ]);

        $response = $this->actingAs($this->exporter)
            ->postJson('/api/v1/exporter/orders/order-004/confirm', []);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'code' => 'INVALID_STATUS_TRANSITION',
            ]);
    }

    public function test_confirm_order_not_found()
    {
        $response = $this->actingAs($this->exporter)
            ->postJson('/api/v1/exporter/orders/invalid-order-id/confirm', []);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code' => 'NOT_FOUND',
            ]);
    }

    public function test_confirm_order_data_isolation_forbidden()
    {
        // Create another exporter
        $exporter2 = User::factory()->create(['role' => 'exporter']);

        $response = $this->actingAs($exporter2)
            ->postJson('/api/v1/exporter/orders/order-001/confirm', []);

        // Should get 403 or 404 (data isolation)
        $this->assertThat(
            $response->status(),
            $this->logicalOr(
                $this->equalTo(403),
                $this->equalTo(404)
            )
        );
    }

    public function test_confirm_order_unauthorized_without_auth()
    {
        $response = $this->postJson('/api/v1/exporter/orders/order-001/confirm', []);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    public function test_confirm_order_forbidden_with_farmer_role()
    {
        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/exporter/orders/order-001/confirm', []);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }

    public function test_confirm_order_forbidden_with_buyer_role()
    {
        $response = $this->actingAs($this->buyer)
            ->postJson('/api/v1/exporter/orders/order-001/confirm', []);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }
}

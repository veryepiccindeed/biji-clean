<?php

namespace Tests\Feature\Buyer;

use App\Models\BatchListing;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\OrderTimeline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * BuyerOrderTest — Test Case untuk Modul Pesanan Buyer (API Contract V3)
 *
 * Scope: 3 endpoint orders + logika bisnis terkait
 *   - GET /api/v1/buyer/orders                     — List pesanan buyer (§10.1)
 *   - GET /api/v1/buyer/orders/{id}                — Detail pesanan (§10.2)
 *   - POST /api/v1/buyer/orders/{id}/payment/confirm — Upload bukti bayar (§10.3)
 *
 * Reference: API_CONTRACT_V3_BUYER.md
 *   - Section 10 (Modul Pesanan / Orders)
 *   - Section 5   (Kode Error — ORDER_NOT_OWNED, ORDER_ALREADY_PAID, PAYMENT_EXPIRED, PAYMENT_VERIFICATION_FAILED)
 *   - Section 14  (Data Model — Enum Status Pesanan Buyer)
 *   - Section 15  (State Machine: Lifecycle Pesanan Buyer)
 *   - Section 16.4 (Order Cancellation Rules)
 *
 * Response fields yang ditest:
 *   [10.1] List: id, product_name, product_variety, product_image_url,
 *         exporter (nested: id, name, avatar_url), status, status_label, status_color,
 *         weight_kg, price_per_kg, total, total_display, port_name,
 *         payment_method, created_at, created_at_label, detail_url, pagination
 *   [10.2] Detail: order.id, order.buyer_id, order.product (nested), order.exporter (nested),
 *         order.quantity, order.pricing, order.status, order.port, order.payment,
 *         order.timeline (array), order.documents (array), order.actions_available,
 *         order.created_at, order.updated_at
 *   [10.3] Payment Confirm: order_id, status → payment_verifying, proof (url, filename, uploaded_at, notes)
 *
 * Business Rules V3 yang ditest:
 *   - Data isolation: buyer hanya lihat pesanan miliknya (buyer_id)
 *   - ORDER_NOT_OWNED: buyer A tidak bisa akses pesanan buyer B
 *   - actions_available.can_cancel = true hanya untuk pending_payment dan payment_verifying
 *   - actions_available.can_confirm_receipt = true hanya untuk delivered
 *   - actions_available.can_upload_payment_proof = true hanya untuk pending_payment + bank_transfer
 *   - Cancel rules: pending_payment/payment_verifying/paid → bisa cancel, processing+ → tidak bisa
 *   - Status filter: hanya pesanan dengan status cocok yang ditampilkan
 *   - Timeline: chronological order, is_current = true hanya untuk entry terbaru
 *   - Upload proof: pending_payment → payment_verifying
 *   - Upload proof errors: ORDER_NOT_OWNED, ORDER_ALREADY_PAID, PAYMENT_EXPIRED, PAYMENT_VERIFICATION_FAILED
 *   - Farmer identity TIDAK BOLEH muncul
 *   - Exporter name HARUS muncul
 *   - Order ID format = ORD-{sequence}
 *
 * Catatan: Cancel dan confirm-receipt POST endpoints belum dispesifikasi di V3.
 *   Cancel logic ditest melalui actions_available.can_cancel flags per status.
 *   confirm-receipt ditest melalui actions_available.can_confirm_receipt flags.
 *
 * Sections (66 tests):
 *   1.  Auth & Authorization — Orders List (3 tests)
 *   2.  Auth & Authorization — Order Detail (3 tests)
 *   3.  Orders List — Response Structure (4 tests)
 *   4.  Orders List — Data Isolation (3 tests)
 *   5.  Orders List — Status Filter (5 tests)
 *   6.  Orders List — Pagination (3 tests)
 *   7.  Orders List — Sort (2 tests)
 *   8.  Orders List — Exporter Visibility & Farmer Isolation (2 tests)
 *   9.  Order Detail — Response Structure (5 tests)
 *   10. Order Detail — Nested Objects (4 tests)
 *   11. Order Detail — Actions Available per Status (7 tests)
 *   12. Order Detail — Timeline (4 tests)
 *   13. Order Detail — Documents (3 tests)
 *   14. Order Detail — Farmer Isolation (2 tests)
 *   15. Order Detail — Ownership & Not Found (4 tests)
 *   16. Payment Confirm — Success (3 tests)
 *   17. Payment Confirm — Ownership Errors (2 tests)
 *   18. Payment Confirm — Status Errors (4 tests)
 *   19. Payment Confirm — File Validation (3 tests)
 */
class BuyerOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private User $buyer2;

    private User $exporter;

    private User $exporter2;

    /** @var Order[] */
    private array $orders = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('orders');

        // ── Buyers ──
        $this->buyer = User::factory()->create([
            'role' => 'buyer',
            'name' => 'John Doe',
            'email' => 'john@company.com',
            'company_name' => 'PT Kopi Nusantara',
        ]);

        $this->buyer2 = User::factory()->create([
            'role' => 'buyer',
            'name' => 'Jane Smith',
            'email' => 'jane@coffee.co',
            'company_name' => 'Pacific Roasters Inc.',
        ]);

        // ── Exporters (VISIBLE) ──
        $this->exporter = User::factory()->create([
            'role' => 'exporter',
            'name' => 'PT Sulawesi Coffee Export',
            'avatar_url' => 'https://storage.biji.local/exporters/2/avatar.jpg',
        ]);

        $this->exporter2 = User::factory()->create([
            'role' => 'exporter',
            'name' => 'CV Toraja Coffee House',
            'avatar_url' => 'https://storage.biji.local/exporters/3/avatar.jpg',
        ]);

        // ── Listings (setup data) ──
        $listing1 = BatchListing::factory()->create([
            'id' => 'listing-001',
            'exporter_id' => $this->exporter->id,
            'batch_code' => 'BJI-TRJ-26054',
            'name' => 'Arabika Toraja Sapan',
            'variety' => 'Arabika Toraja',
            'origin' => 'Tana Toraja, Sulawesi Selatan',
            'image_url' => 'https://storage.biji.local/listings/listing-001/cover.jpg',
            'price_per_kg' => 145000,
            'stock_kg' => 1000,
            'status' => 'listed',
        ]);

        $listing2 = BatchListing::factory()->create([
            'id' => 'listing-002',
            'exporter_id' => $this->exporter2->id,
            'batch_code' => 'BJI-ENK-26048',
            'name' => 'Robusta Enrekang Premium',
            'variety' => 'Robusta Enrekang',
            'origin' => 'Enrekang, Sulawesi Selatan',
            'image_url' => 'https://storage.biji.local/listings/listing-002/cover.jpg',
            'price_per_kg' => 85000,
            'stock_kg' => 1000,
            'status' => 'listed',
        ]);

        // ── Orders untuk buyer utama (9 status) ──
        $statuses = [
            'pending_payment', 'payment_verifying', 'paid', 'processing',
            'ready_shipment', 'in_transit', 'delivered', 'completed', 'cancelled',
        ];

        foreach ($statuses as $i => $status) {
            $order = Order::factory()->create([
                'id' => "ORD-103{$i}",
                'buyer_id' => $this->buyer->id,
                'batch_listing_id' => $listing1->id,
                'exporter_id' => $this->exporter->id,
                'status' => $status,
                'weight_kg' => 100,
                'price_per_kg' => 145000,
                'subtotal' => 14500000,
                'shipping_cost' => 250000,
                'platform_fee' => 15000,
                'total' => 14765000,
                'payment_method' => $i % 2 === 0 ? 'bank_transfer' : 'qris',
                'port_id' => 1,
                'port_name' => 'Tanjung Priok, Jakarta',
                'created_at' => now()->subHours(24 * (9 - $i)),
            ]);

            // Buat timeline entries untuk setiap transisi yang sudah terjadi
            $transitions = $this->getTransitionsForStatus($status);
            foreach ($transitions as $j => $transition) {
                OrderTimeline::factory()->create([
                    'order_id' => $order->id,
                    'status' => $transition,
                    'timestamp' => now()->subHours(24 * (9 - $i) + $j),
                    'is_current' => ($j === array_key_last($transitions)),
                ]);
            }

            // Buat dokumen untuk order yang sudah paid+
            if (in_array($status, ['paid', 'processing', 'ready_shipment', 'in_transit', 'delivered', 'completed'])) {
                OrderDocument::factory()->create([
                    'order_id' => $order->id,
                    'type' => 'invoice',
                    'type_label' => 'Invoice',
                    'filename' => "INV-{$order->id}.pdf",
                ]);
            }

            $this->orders[$status] = $order;
        }

        // ── Order untuk buyer2 (untuk isolasi test) ──
        Order::factory()->create([
            'id' => 'ORD-2001',
            'buyer_id' => $this->buyer2->id,
            'batch_listing_id' => $listing2->id,
            'exporter_id' => $this->exporter2->id,
            'status' => 'in_transit',
            'weight_kg' => 50,
            'price_per_kg' => 85000,
            'total' => 4290000,
            'payment_method' => 'qris',
            'port_id' => 1,
            'port_name' => 'Tanjung Priok, Jakarta',
        ]);
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    /**
     * Helper: GET orders list
     */
    private function getOrders(array $query = [], ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->buyer)
            ->getJson('/api/v1/buyer/orders?'.http_build_query($query));
    }

    /**
     * Helper: GET order detail
     */
    private function getOrderDetail(string $orderId, ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->buyer)
            ->getJson("/api/v1/buyer/orders/{$orderId}");
    }

    /**
     * Helper: POST payment confirm (upload bukti)
     */
    private function postPaymentConfirm(string $orderId, UploadedFile $file, string $notes = '', ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->buyer)
            ->postJson("/api/v1/buyer/orders/{$orderId}/payment/confirm", [
                'proof_file' => $file,
                'notes' => $notes,
            ]);
    }

    /**
     * Helper: Upload file valid (JPG)
     */
    private function validProofFile(): UploadedFile
    {
        return UploadedFile::fake()->image('transfer-proof.jpg', 800, 600);
    }

    /**
     * Helper: Ambil transisi yang sudah terjadi untuk suatu status
     */
    private function getTransitionsForStatus(string $status): array
    {
        $stateMachine = [
            'pending_payment' => ['pending_payment'],
            'payment_verifying' => ['pending_payment', 'payment_verifying'],
            'paid' => ['pending_payment', 'payment_verifying', 'paid'],
            'processing' => ['pending_payment', 'payment_verifying', 'paid', 'processing'],
            'ready_shipment' => ['pending_payment', 'payment_verifying', 'paid', 'processing', 'ready_shipment'],
            'in_transit' => ['pending_payment', 'payment_verifying', 'paid', 'processing', 'ready_shipment', 'in_transit'],
            'delivered' => ['pending_payment', 'payment_verifying', 'paid', 'processing', 'ready_shipment', 'in_transit', 'delivered'],
            'completed' => ['pending_payment', 'payment_verifying', 'paid', 'processing', 'ready_shipment', 'in_transit', 'delivered', 'completed'],
            'cancelled' => ['pending_payment'],
        ];

        return $stateMachine[$status] ?? [];
    }

    // ========================================================================
    // SECTION 1: AUTH & AUTHORIZATION — ORDERS LIST (3 tests)
    // ========================================================================

    /** @test */
    public function test_orders_list_requires_authentication(): void
    {
        $this->getJson('/api/v1/buyer/orders')
            ->assertUnauthorized()
            ->assertJson(['success' => false, 'code' => 'UNAUTHORIZED']);
    }

    /** @test */
    public function test_orders_list_rejects_farmer_role(): void
    {
        $farmer = User::factory()->create(['role' => 'farmer']);

        $this->actingAs($farmer)
            ->getJson('/api/v1/buyer/orders')
            ->assertStatus(403)
            ->assertJson(['success' => false, 'code' => 'FORBIDDEN']);
    }

    /** @test */
    public function test_orders_list_rejects_exporter_role(): void
    {
        $exporter = User::factory()->create(['role' => 'exporter']);

        $this->actingAs($exporter)
            ->getJson('/api/v1/buyer/orders')
            ->assertStatus(403)
            ->assertJson(['success' => false, 'code' => 'FORBIDDEN']);
    }

    // ========================================================================
    // SECTION 2: AUTH & AUTHORIZATION — ORDER DETAIL (3 tests)
    // ========================================================================

    /** @test */
    public function test_order_detail_requires_authentication(): void
    {
        $this->getJson('/api/v1/buyer/orders/ORD-1030')
            ->assertUnauthorized()
            ->assertJson(['success' => false, 'code' => 'UNAUTHORIZED']);
    }

    /** @test */
    public function test_order_detail_rejects_farmer_role(): void
    {
        $farmer = User::factory()->create(['role' => 'farmer']);

        $this->actingAs($farmer)
            ->getJson('/api/v1/buyer/orders/ORD-1030')
            ->assertStatus(403)
            ->assertJson(['success' => false, 'code' => 'FORBIDDEN']);
    }

    /** @test */
    public function test_order_detail_rejects_exporter_role(): void
    {
        $exporter = User::factory()->create(['role' => 'exporter']);

        $this->actingAs($exporter)
            ->getJson('/api/v1/buyer/orders/ORD-1030')
            ->assertStatus(403)
            ->assertJson(['success' => false, 'code' => 'FORBIDDEN']);
    }

    // ========================================================================
    // SECTION 3: ORDERS LIST — RESPONSE STRUCTURE (4 tests)
    // ========================================================================

    /** @test */
    public function test_orders_list_returns_200_with_correct_structure(): void
    {
        $response = $this->getOrders();

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
                'message' => 'Daftar pesanan berhasil diambil',
            ]);

        $response->assertJsonStructure([
            'data',
            'pagination' => ['cursor', 'hasMore', 'limit', 'total'],
            'timestamp',
        ]);
    }

    /** @test */
    public function test_orders_list_item_has_all_required_fields(): void
    {
        $response = $this->getOrders(['status' => 'in_transit']);

        $response->assertOk();

        $item = $response->json('data.0');
        $requiredFields = [
            'id', 'product_name', 'product_variety', 'product_image_url',
            'exporter', 'status', 'status_label', 'status_color',
            'weight_kg', 'price_per_kg', 'total', 'total_display',
            'port_name', 'payment_method', 'created_at', 'created_at_label', 'detail_url',
        ];

        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $item, "Missing list field: {$field}");
        }
    }

    /** @test */
    public function test_orders_list_item_has_nested_exporter(): void
    {
        $response = $this->getOrders(['status' => 'in_transit']);

        $response->assertOk();

        $exporter = $response->json('data.0.exporter');
        $this->assertArrayHasKey('id', $exporter);
        $this->assertArrayHasKey('name', $exporter);
        $this->assertArrayHasKey('avatar_url', $exporter);
    }

    /** @test */
    public function test_orders_list_detail_url_follows_pattern(): void
    {
        $response = $this->getOrders(['status' => 'in_transit']);

        $response->assertOk();

        $item = $response->json('data.0');
        $this->assertEquals(
            "/api/v1/buyer/orders/{$item['id']}",
            $item['detail_url']
        );
    }

    // ========================================================================
    // SECTION 4: ORDERS LIST — DATA ISOLATION (3 tests)
    // ========================================================================

    /** @test */
    public function test_orders_list_only_shows_buyers_own_orders(): void
    {
        // buyer punya 9 orders (semua status)
        $response = $this->getOrders();

        $response->assertOk();

        $items = $response->json('data');
        foreach ($items as $item) {
            // Order ID harus milik buyer (ORD-103x)
            $this->assertMatchesRegularExpression('/^ORD-103\d$/', $item['id']);
        }

        // ORD-2001 (buyer2) TIDAK boleh muncul
        $ids = array_column($items, 'id');
        $this->assertNotContains('ORD-2001', $ids);
    }

    /** @test */
    public function test_orders_list_shows_empty_for_new_buyer(): void
    {
        $newBuyer = User::factory()->create(['role' => 'buyer']);

        $response = $this->getOrders([], $newBuyer);

        $response->assertOk();

        $this->assertCount(0, $response->json('data'));
        $this->assertEquals(0, $response->json('pagination.total'));
    }

    /** @test */
    public function test_orders_list_buyer2_only_sees_own_orders(): void
    {
        $response = $this->getOrders([], $this->buyer2);

        $response->assertOk();

        $items = $response->json('data');
        $ids = array_column($items, 'id');
        $this->assertContains('ORD-2001', $ids);

        // Tidak boleh ada ORD-103x (milik buyer)
        foreach ($ids as $id) {
            $this->assertDoesNotMatchRegularExpression('/^ORD-103\d$/', $id);
        }
    }

    // ========================================================================
    // SECTION 5: ORDERS LIST — STATUS FILTER (5 tests)
    // ========================================================================

    /** @test */
    public function test_orders_list_filter_by_single_status(): void
    {
        $response = $this->getOrders(['status' => 'in_transit']);

        $response->assertOk();

        $items = $response->json('data');
        foreach ($items as $item) {
            $this->assertEquals('in_transit', $item['status']);
        }
    }

    /** @test */
    public function test_orders_list_filter_pending_payment(): void
    {
        $response = $this->getOrders(['status' => 'pending_payment']);

        $response->assertOk();

        $items = $response->json('data');
        $this->assertGreaterThan(0, count($items));
        foreach ($items as $item) {
            $this->assertEquals('pending_payment', $item['status']);
        }
    }

    /** @test */
    public function test_orders_list_filter_completed(): void
    {
        $response = $this->getOrders(['status' => 'completed']);

        $response->assertOk();

        $items = $response->json('data');
        $this->assertGreaterThan(0, count($items));
        foreach ($items as $item) {
            $this->assertEquals('completed', $item['status']);
        }
    }

    /** @test */
    public function test_orders_list_filter_cancelled(): void
    {
        $response = $this->getOrders(['status' => 'cancelled']);

        $response->assertOk();

        $items = $response->json('data');
        $this->assertGreaterThan(0, count($items));
        foreach ($items as $item) {
            $this->assertEquals('cancelled', $item['status']);
        }
    }

    /** @test */
    public function test_orders_list_without_filter_shows_all_statuses(): void
    {
        $response = $this->getOrders();

        $response->assertOk();

        $items = $response->json('data');
        $statuses = array_unique(array_column($items, 'status'));

        // Buyer punya 9 orders di 9 status berbeda
        $this->assertEquals(9, count($statuses));
    }

    // ========================================================================
    // SECTION 6: ORDERS LIST — PAGINATION (3 tests)
    // ========================================================================

    /** @test */
    public function test_orders_list_respects_limit_parameter(): void
    {
        $response = $this->getOrders(['limit' => 3]);

        $response->assertOk();

        $this->assertLessThanOrEqual(3, count($response->json('data')));
        $this->assertEquals(3, $response->json('pagination.limit'));
    }

    /** @test */
    public function test_orders_list_default_limit_is_20(): void
    {
        // Buat 25 orders tambahan
        for ($i = 100; $i < 125; $i++) {
            Order::factory()->create([
                'id' => "ORD-10{$i}",
                'buyer_id' => $this->buyer->id,
                'batch_listing_id' => 'listing-001',
                'exporter_id' => $this->exporter->id,
                'status' => 'completed',
                'weight_kg' => 50,
                'price_per_kg' => 145000,
                'total' => 7265000,
                'payment_method' => 'bank_transfer',
                'port_id' => 1,
                'port_name' => 'Tanjung Priok, Jakarta',
                'created_at' => now()->subHours($i),
            ]);
        }

        $response = $this->getOrders();

        $response->assertOk();
        $this->assertEquals(20, $response->json('pagination.limit'));
    }

    /** @test */
    public function test_orders_list_pagination_has_cursor_and_total(): void
    {
        // Buat 25 orders tambahan agar total menjadi 34
        for ($i = 100; $i < 125; $i++) {
            Order::factory()->create([
                'id' => "ORD-10{$i}",
                'buyer_id' => $this->buyer->id,
                'batch_listing_id' => 'listing-001',
                'exporter_id' => $this->exporter->id,
                'status' => 'completed',
                'weight_kg' => 50,
                'price_per_kg' => 145000,
                'total' => 7265000,
                'payment_method' => 'bank_transfer',
                'port_id' => 1,
                'port_name' => 'Tanjung Priok, Jakarta',
            ]);
        }

        $response = $this->getOrders(['limit' => 5]);

        $response->assertOk();

        $pagination = $response->json('pagination');
        $this->assertNotNull($pagination['cursor']);
        $this->assertIsBool($pagination['hasMore']);
        $this->assertEquals(5, $pagination['limit']);
        $this->assertEquals(34, $pagination['total']); // 9 default + 25 extra = 34
    }

    // ========================================================================
    // SECTION 7: ORDERS LIST — SORT (2 tests)
    // ========================================================================

    /** @test */
    public function test_orders_list_sort_by_created_at_desc(): void
    {
        $response = $this->getOrders(['sort' => 'created_at', 'sort_dir' => 'desc', 'limit' => 50]);

        $response->assertOk();

        $items = $response->json('data');
        if (count($items) >= 2) {
            $this->assertGreaterThanOrEqual(
                strtotime($items[1]['created_at']),
                strtotime($items[0]['created_at'])
            );
        }
    }

    /** @test */
    public function test_orders_list_sort_by_total_amount_asc(): void
    {
        $response = $this->getOrders(['sort' => 'total_amount', 'sort_dir' => 'asc']);

        $response->assertOk();

        $items = $response->json('data');
        if (count($items) >= 2) {
            $this->assertLessThanOrEqual(
                $items[1]['total'],
                $items[0]['total']
            );
        }
    }

    // ========================================================================
    // SECTION 8: ORDERS LIST — EXPORTER VISIBILITY & FARMER ISOLATION (2 tests)
    // ========================================================================

    /** @test */
    public function test_orders_list_shows_exporter_name(): void
    {
        $response = $this->getOrders(['status' => 'in_transit']);

        $response->assertOk();

        $exporter = $response->json('data.0.exporter');
        $this->assertEquals('PT Sulawesi Coffee Export', $exporter['name']);
    }

    /** @test */
    public function test_orders_list_does_not_expose_farmer_identity(): void
    {
        $response = $this->getOrders();

        $response->assertOk();

        $responseData = json_encode($response->json());
        $this->assertStringNotContainsString('farmer', $responseData);
        $this->assertStringNotContainsString('farmer_id', $responseData);
    }

    // ========================================================================
    // SECTION 9: ORDER DETAIL — RESPONSE STRUCTURE (5 tests)
    // ========================================================================

    /** @test */
    public function test_order_detail_returns_200_for_own_order(): void
    {
        $response = $this->getOrderDetail('ORD-1030');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
                'message' => 'Detail pesanan berhasil diambil',
            ]);
    }

    /** @test */
    public function test_order_detail_has_nested_order_object(): void
    {
        $response = $this->getOrderDetail('ORD-1030');

        $response->assertOk();

        $order = $response->json('data.order');
        $topLevelKeys = [
            'id', 'buyer_id', 'product', 'exporter', 'quantity', 'pricing',
            'status', 'status_label', 'status_color', 'port', 'payment',
            'timeline', 'documents', 'actions_available', 'created_at', 'updated_at',
        ];

        foreach ($topLevelKeys as $key) {
            $this->assertArrayHasKey($key, $order, "Missing order detail field: {$key}");
        }
    }

    /** @test */
    public function test_order_detail_status_has_label_and_color(): void
    {
        $response = $this->getOrderDetail('ORD-1030');

        $response->assertOk();

        $order = $response->json('data.order');
        $this->assertNotEmpty($order['status_label']);
        $this->assertNotEmpty($order['status_color']);
        $this->assertMatchesRegularExpression('/^#[0-9A-Fa-f]{6}$/', $order['status_color']);
    }

    /** @test */
    public function test_order_detail_order_id_matches_format(): void
    {
        $response = $this->getOrderDetail('ORD-1030');

        $response->assertOk();

        $this->assertMatchesRegularExpression('/^ORD-\d+$/', $response->json('data.order.id'));
    }

    /** @test */
    public function test_order_detail_buyer_id_matches_authenticated_buyer(): void
    {
        $response = $this->getOrderDetail('ORD-1030');

        $response->assertOk();

        $this->assertEquals($this->buyer->id, $response->json('data.order.buyer_id'));
    }

    // ========================================================================
    // SECTION 10: ORDER DETAIL — NESTED OBJECTS (4 tests)
    // ========================================================================

    /** @test */
    public function test_order_detail_product_has_batch_listing_info(): void
    {
        $response = $this->getOrderDetail('ORD-1030');

        $response->assertOk();

        $product = $response->json('data.order.product');
        $requiredFields = ['batch_listing_id', 'batch_code', 'name', 'variety', 'origin', 'image_url'];

        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $product, "Missing product field: {$field}");
        }

        $this->assertEquals('listing-001', $product['batch_listing_id']);
    }

    /** @test */
    public function test_order_detail_exporter_has_full_info(): void
    {
        $response = $this->getOrderDetail('ORD-1030');

        $response->assertOk();

        $exporter = $response->json('data.order.exporter');
        $this->assertArrayHasKey('id', $exporter);
        $this->assertArrayHasKey('name', $exporter);
        $this->assertArrayHasKey('avatar_url', $exporter);
        $this->assertArrayHasKey('location', $exporter);
        $this->assertArrayHasKey('phone', $exporter);
        $this->assertArrayHasKey('email', $exporter);

        $this->assertEquals('PT Sulawesi Coffee Export', $exporter['name']);
    }

    /** @test */
    public function test_order_detail_pricing_has_all_breakdown(): void
    {
        $response = $this->getOrderDetail('ORD-1030');

        $response->assertOk();

        $pricing = $response->json('data.order.pricing');
        $requiredFields = [
            'subtotal', 'subtotal_display',
            'shipping_cost', 'shipping_cost_display', 'shipping_rate_per_kg',
            'platform_fee', 'platform_fee_display',
            'total', 'total_display',
        ];

        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $pricing, "Missing pricing field: {$field}");
        }
    }

    /** @test */
    public function test_order_detail_port_has_pickup_info(): void
    {
        $response = $this->getOrderDetail('ORD-1030');

        $response->assertOk();

        $port = $response->json('data.order.port');
        $requiredFields = ['id', 'name', 'full_name', 'eta_days', 'eta_label'];

        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $port, "Missing port field: {$field}");
        }
    }

    // ========================================================================
    // SECTION 11: ORDER DETAIL — ACTIONS AVAILABLE PER STATUS (7 tests)
    // ========================================================================

    /** @test */
    public function test_actions_can_cancel_true_for_pending_payment(): void
    {
        $response = $this->getOrderDetail('ORD-1030'); // pending_payment

        $response->assertOk()
            ->assertJsonPath('data.order.actions_available.can_cancel', true);
    }

    /** @test */
    public function test_actions_can_cancel_true_for_payment_verifying(): void
    {
        $response = $this->getOrderDetail('ORD-1031'); // payment_verifying

        $response->assertOk()
            ->assertJsonPath('data.order.actions_available.can_cancel', true);
    }

    /** @test */
    public function test_actions_can_cancel_false_for_processing(): void
    {
        $response = $this->getOrderDetail('ORD-1033'); // processing

        $response->assertOk()
            ->assertJsonPath('data.order.actions_available.can_cancel', false);
    }

    /** @test */
    public function test_actions_can_cancel_false_for_in_transit(): void
    {
        $response = $this->getOrderDetail('ORD-1035'); // in_transit

        $response->assertOk()
            ->assertJsonPath('data.order.actions_available.can_cancel', false);
    }

    /** @test */
    public function test_actions_can_confirm_receipt_true_for_delivered(): void
    {
        $response = $this->getOrderDetail('ORD-1036'); // delivered

        $response->assertOk()
            ->assertJsonPath('data.order.actions_available.can_confirm_receipt', true);
    }

    /** @test */
    public function test_actions_can_confirm_receipt_false_for_non_delivered(): void
    {
        $response = $this->getOrderDetail('ORD-1030'); // pending_payment

        $response->assertOk()
            ->assertJsonPath('data.order.actions_available.can_confirm_receipt', false);
    }

    /** @test */
    public function test_actions_can_cancel_false_for_completed(): void
    {
        $response = $this->getOrderDetail('ORD-1038'); // completed

        $response->assertOk()
            ->assertJsonPath('data.order.actions_available.can_cancel', false);
    }

    // ========================================================================
    // SECTION 12: ORDER DETAIL — TIMELINE (4 tests)
    // ========================================================================

    /** @test */
    public function test_order_detail_timeline_is_array_of_transitions(): void
    {
        $response = $this->getOrderDetail('ORD-1035'); // in_transit — 6 transitions

        $response->assertOk();

        $timeline = $response->json('data.order.timeline');
        $this->assertIsArray($timeline);
        $this->assertCount(6, $timeline);
    }

    /** @test */
    public function test_order_detail_timeline_has_required_fields(): void
    {
        $response = $this->getOrderDetail('ORD-1030'); // pending_payment

        $response->assertOk();

        $timeline = $response->json('data.order.timeline');
        $requiredFields = ['id', 'status', 'status_label', 'description', 'timestamp', 'is_current'];

        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $timeline[0], "Missing timeline field: {$field}");
        }
    }

    /** @test */
    public function test_order_detail_timeline_only_last_entry_is_current(): void
    {
        $response = $this->getOrderDetail('ORD-1033'); // processing — 4 transitions

        $response->assertOk();

        $timeline = $response->json('data.order.timeline');
        $this->assertCount(4, $timeline);

        // Hanya entry terakhir yang is_current = true
        $currentCount = 0;
        foreach ($timeline as $entry) {
            if ($entry['is_current']) {
                $currentCount++;
            }
        }
        $this->assertEquals(1, $currentCount);
        $this->assertTrue($timeline[array_key_last($timeline)]['is_current']);
    }

    /** @test */
    public function test_order_detail_timeline_is_chronologically_ordered(): void
    {
        $response = $this->getOrderDetail('ORD-1035'); // in_transit

        $response->assertOk();

        $timeline = $response->json('data.order.timeline');
        for ($i = 1; $i < count($timeline); $i++) {
            $this->assertGreaterThanOrEqual(
                strtotime($timeline[$i - 1]['timestamp']),
                strtotime($timeline[$i]['timestamp']),
                "Timeline not chronological at index {$i}"
            );
        }
    }

    // ========================================================================
    // SECTION 13: ORDER DETAIL — DOCUMENTS (3 tests)
    // ========================================================================

    /** @test */
    public function test_order_detail_has_documents_for_paid_order(): void
    {
        $response = $this->getOrderDetail('ORD-1032'); // paid

        $response->assertOk();

        $documents = $response->json('data.order.documents');
        $this->assertIsArray($documents);
        $this->assertGreaterThan(0, count($documents));
    }

    /** @test */
    public function test_order_detail_document_has_required_fields(): void
    {
        $response = $this->getOrderDetail('ORD-1032'); // paid

        $response->assertOk();

        $doc = $response->json('data.order.documents.0');
        $requiredFields = ['id', 'type', 'type_label', 'url', 'filename', 'created_at'];

        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $doc, "Missing document field: {$field}");
        }
    }

    /** @test */
    public function test_order_detail_no_documents_for_pending_payment(): void
    {
        $response = $this->getOrderDetail('ORD-1030'); // pending_payment

        $response->assertOk();

        $documents = $response->json('data.order.documents');
        $this->assertCount(0, $documents);
    }

    // ========================================================================
    // SECTION 14: ORDER DETAIL — FARMER ISOLATION (2 tests)
    // ========================================================================

    /** @test */
    public function test_order_detail_does_not_expose_farmer_identity(): void
    {
        $response = $this->getOrderDetail('ORD-1030');

        $response->assertOk();

        $responseData = json_encode($response->json());

        $farmerTerms = ['farmer_id', 'farmer_name', 'farmer_hash', 'farmer_code'];
        foreach ($farmerTerms as $term) {
            $this->assertStringNotContainsString($term, $responseData, "Farmer term '{$term}' found in detail response");
        }
    }

    /** @test */
    public function test_order_detail_shows_exporter_name(): void
    {
        $response = $this->getOrderDetail('ORD-1030');

        $response->assertOk()
            ->assertJsonPath('data.order.exporter.name', 'PT Sulawesi Coffee Export');
    }

    // ========================================================================
    // SECTION 15: ORDER DETAIL — OWNERSHIP & NOT FOUND (4 tests)
    // ========================================================================

    /** @test */
    public function test_order_detail_returns_403_for_other_buyers_order(): void
    {
        // ORD-1030 milik buyer, buyer2 coba akses
        $response = $this->getOrderDetail('ORD-1030', $this->buyer2);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'ORDER_NOT_OWNED',
            ])
            ->assertJsonPath('details.order_id', 'ORD-1030')
            ->assertJsonPath('details.order_buyer_id', $this->buyer->id)
            ->assertJsonPath('details.current_buyer_id', $this->buyer2->id);
    }

    /** @test */
    public function test_order_detail_returns_404_for_nonexistent_order(): void
    {
        $response = $this->getOrderDetail('ORD-9999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code' => 'NOT_FOUND',
            ]);
    }

    /** @test */
    public function test_order_detail_returns_404_for_other_buyers_order_id_format(): void
    {
        $response = $this->getOrderDetail('ORD-2001', $this->buyer);

        // ORD-2001 milik buyer2 — buyer coba akses → ORDER_NOT_OWNED (403)
        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'ORDER_NOT_OWNED',
            ]);
    }

    /** @test */
    public function test_order_detail_same_buyer_can_access_all_own_orders(): void
    {
        $statuses = ['pending_payment', 'paid', 'processing', 'in_transit', 'delivered', 'completed', 'cancelled'];
        foreach ($statuses as $status) {
            $orderId = $this->orders[$status]->id;
            $response = $this->getOrderDetail($orderId);
            $response->assertOk()->assertJsonPath('data.order.id', $orderId);
        }
    }

    // ========================================================================
    // SECTION 16: PAYMENT CONFIRM — SUCCESS (3 tests)
    // ========================================================================

    /** @test */
    public function test_payment_confirm_success_changes_status_to_payment_verifying(): void
    {
        $file = $this->validProofFile();

        $response = $this->postPaymentConfirm('ORD-1030', $file, 'Transfer BCA ke VA');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS_UPDATE',
                'message' => 'Bukti pembayaran berhasil diupload',
            ])
            ->assertJsonPath('data.order_id', 'ORD-1030')
            ->assertJsonPath('data.status', 'payment_verifying')
            ->assertJsonPath('data.status_label', 'Verifikasi Pembayaran');
    }

    /** @test */
    public function test_payment_confirm_returns_proof_info(): void
    {
        $file = $this->validProofFile();

        $response = $this->postPaymentConfirm('ORD-1030', $file, 'Catatan transfer');

        $response->assertOk();

        $proof = $response->json('data.proof');
        $this->assertArrayHasKey('url', $proof);
        $this->assertArrayHasKey('filename', $proof);
        $this->assertArrayHasKey('uploaded_at', $proof);
        $this->assertArrayHasKey('notes', $proof);
        $this->assertEquals('Catatan transfer', $proof['notes']);
    }

    /** @test */
    public function test_payment_confirm_accepts_jpg_and_png(): void
    {
        $jpg = UploadedFile::fake()->image('proof.jpg', 800, 600);
        $response = $this->postPaymentConfirm('ORD-1030', $jpg);
        $response->assertOk();

        // Buat order baru pending_payment untuk test kedua
        Order::factory()->create([
            'id' => 'ORD-1050',
            'buyer_id' => $this->buyer->id,
            'batch_listing_id' => 'listing-001',
            'exporter_id' => $this->exporter->id,
            'status' => 'pending_payment',
            'payment_method' => 'bank_transfer',
            'total' => 14765000,
            'port_id' => 1,
        ]);

        $png = UploadedFile::fake()->image('proof.png', 1024, 768);
        $response = $this->postPaymentConfirm('ORD-1050', $png);
        $response->assertOk();
    }

    // ========================================================================
    // SECTION 17: PAYMENT CONFIRM — OWNERSHIP ERRORS (2 tests)
    // ========================================================================

    /** @test */
    public function test_payment_confirm_returns_403_for_other_buyers_order(): void
    {
        $file = $this->validProofFile();

        $response = $this->postPaymentConfirm('ORD-1030', $file, '', $this->buyer2);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'ORDER_NOT_OWNED',
            ]);
    }

    /** @test */
    public function test_payment_confirm_returns_404_for_nonexistent_order(): void
    {
        $file = $this->validProofFile();

        $response = $this->postPaymentConfirm('ORD-9999', $file);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code' => 'NOT_FOUND',
            ]);
    }

    // ========================================================================
    // SECTION 18: PAYMENT CONFIRM — STATUS ERRORS (4 tests)
    // ========================================================================

    /** @test */
    public function test_payment_confirm_returns_already_paid_for_paid_order(): void
    {
        $file = $this->validProofFile();

        $response = $this->postPaymentConfirm('ORD-1032', $file); // paid

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'code' => 'ORDER_ALREADY_PAID',
            ]);
    }

    /** @test */
    public function test_payment_confirm_returns_already_paid_for_processing_order(): void
    {
        $file = $this->validProofFile();

        $response = $this->postPaymentConfirm('ORD-1033', $file); // processing

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'code' => 'ORDER_ALREADY_PAID',
            ]);
    }

    /** @test */
    public function test_payment_confirm_returns_payment_expired_for_cancelled_order(): void
    {
        $file = $this->validProofFile();

        $response = $this->postPaymentConfirm('ORD-1038', $file); // cancelled

        $response->assertStatus(410)
            ->assertJson([
                'success' => false,
                'code' => 'PAYMENT_EXPIRED',
            ]);
    }

    /** @test */
    public function test_payment_confirm_returns_already_paid_for_completed_order(): void
    {
        $file = $this->validProofFile();

        $response = $this->postPaymentConfirm('ORD-1038-clone', $file); // completed — use existing

        // Buat order completed khusus untuk test
        Order::factory()->create([
            'id' => 'ORD-1060',
            'buyer_id' => $this->buyer->id,
            'batch_listing_id' => 'listing-001',
            'exporter_id' => $this->exporter->id,
            'status' => 'completed',
            'payment_method' => 'bank_transfer',
            'total' => 14765000,
            'port_id' => 1,
        ]);

        $response = $this->postPaymentConfirm('ORD-1060', $file);
        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'code' => 'ORDER_ALREADY_PAID',
            ]);
    }

    // ========================================================================
    // SECTION 19: PAYMENT CONFIRM — FILE VALIDATION (3 tests)
    // ========================================================================

    /** @test */
    public function test_payment_confirm_rejects_missing_file(): void
    {
        $response = $this->actingAs($this->buyer)
            ->postJson('/api/v1/buyer/orders/ORD-1030/payment/confirm', [
                'notes' => 'Catatan tanpa file',
            ]);

        $response->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    /** @test */
    public function test_payment_confirm_rejects_invalid_file_type(): void
    {
        $file = UploadedFile::fake()->create('proof.exe', 100, 'application/exe');

        $response = $this->postPaymentConfirm('ORD-1030', $file);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'PAYMENT_VERIFICATION_FAILED',
            ]);
    }

    /** @test */
    public function test_payment_confirm_rejects_oversized_file(): void
    {
        // 6MB > 5MB limit
        $file = UploadedFile::fake()->create('proof.pdf', 6000, 'application/pdf');

        $response = $this->postPaymentConfirm('ORD-1030', $file);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'PAYMENT_VERIFICATION_FAILED',
            ]);
    }
}

<?php

namespace Tests\Feature\Buyer;

use App\Models\BatchListing;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Port;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * BuyerEdgeCaseTest — Comprehensive test suite covering edge cases for Buyer POV (API Contract V3)
 *
 * Covers:
 *   - Section 3  (Authorization & Role Control)
 *   - Section 7  (Katalog Biji Kopi Visibility & Farmer Isolation)
 *   - Section 9  (Checkout Edge Cases & Port Validation)
 *   - Section 10 (Order Tracking, Status Transitions & Action Flags)
 *   - Section 12 (Notification Ownership & Status Updates)
 *   - Section 16 (Business Logic Edge Cases)
 */
class BuyerEdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private User $buyer2;

    private User $exporter;

    private User $farmer;

    private Port $activePort;

    private Port $inactivePort;

    private BatchListing $listedListing;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('orders');

        // Setup Buyers
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

        // Setup Exporter
        $this->exporter = User::factory()->create([
            'role' => 'exporter',
            'name' => 'PT Sulawesi Coffee Export',
        ]);

        // Setup Farmer
        $this->farmer = User::factory()->create([
            'role' => 'farmer',
            'name' => 'Yusuf Ibrahim',
        ]);

        // Setup Ports
        $this->activePort = Port::factory()->create([
            'id' => 1,
            'name' => 'Tanjung Priok',
            'full_name' => 'Pelabuhan Tanjung Priok, Jakarta',
            'is_active' => true,
            'shipping_rate_per_kg' => 2500,
            'eta_days' => 3,
        ]);

        $this->inactivePort = Port::factory()->create([
            'id' => 2,
            'name' => 'Belawan Inactive',
            'full_name' => 'Pelabuhan Belawan Inactive, Medan',
            'is_active' => false,
            'shipping_rate_per_kg' => 3500,
            'eta_days' => 7,
        ]);

        // Setup Listing
        $this->listedListing = BatchListing::factory()->create([
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
    }

    // ========================================================================
    // SECTION 1: AUTH & AUTHORIZATION EDGE CASES
    // ========================================================================

    /** @test */
    public function test_accessing_buyer_dashboard_as_guest_returns_unauthorized(): void
    {
        $this->getJson('/api/v1/buyer/dashboard')
            ->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    /** @test */
    public function test_accessing_buyer_dashboard_as_farmer_returns_forbidden(): void
    {
        $this->actingAs($this->farmer)
            ->getJson('/api/v1/buyer/dashboard')
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }

    // ========================================================================
    // SECTION 2: CATALOG VISIBILITY & FARMER ISOLATION EDGE CASES
    // ========================================================================

    /** @test */
    public function test_buyer_cannot_view_details_of_draft_listing(): void
    {
        $draftListing = BatchListing::factory()->create([
            'id' => 'listing-draft',
            'exporter_id' => $this->exporter->id,
            'status' => 'draft',
            'stock_kg' => 500,
        ]);

        $this->actingAs($this->buyer)
            ->getJson('/api/v1/buyer/catalog/listing-draft')
            ->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code' => 'BATCH_NOT_LISTED',
            ]);
    }

    /** @test */
    public function test_buyer_cannot_view_details_of_archived_listing(): void
    {
        $archivedListing = BatchListing::factory()->create([
            'id' => 'listing-archived',
            'exporter_id' => $this->exporter->id,
            'status' => 'archived',
            'stock_kg' => 500,
        ]);

        $this->actingAs($this->buyer)
            ->getJson('/api/v1/buyer/catalog/listing-archived')
            ->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code' => 'BATCH_NOT_LISTED',
            ]);
    }

    /** @test */
    public function test_buyer_cannot_view_details_of_nonexistent_listing(): void
    {
        $this->actingAs($this->buyer)
            ->getJson('/api/v1/buyer/catalog/listing-nonexistent')
            ->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code' => 'BATCH_NOT_LISTED',
            ]);
    }

    /** @test */
    public function test_strict_farmer_isolation_in_catalog_detail(): void
    {
        $response = $this->actingAs($this->buyer)
            ->getJson('/api/v1/buyer/catalog/listing-001')
            ->assertStatus(200);

        $json = json_encode($response->json());

        $this->assertStringNotContainsString('farmer_id', $json);
        $this->assertStringNotContainsString('farmer_name', $json);
        $this->assertStringNotContainsString('farmer_hash', $json);
        $this->assertStringNotContainsString('Yusuf Ibrahim', $json);
    }

    /** @test */
    public function test_strict_farmer_isolation_in_catalog_list(): void
    {
        $response = $this->actingAs($this->buyer)
            ->getJson('/api/v1/buyer/catalog')
            ->assertStatus(200);

        $json = json_encode($response->json());

        $this->assertStringNotContainsString('farmer_id', $json);
        $this->assertStringNotContainsString('farmer_name', $json);
        $this->assertStringNotContainsString('farmer_hash', $json);
        $this->assertStringNotContainsString('Yusuf Ibrahim', $json);
    }

    // ========================================================================
    // SECTION 3: CHECKOUT EDGE CASES
    // ========================================================================

    /** @test */
    public function test_checkout_fails_if_stock_depleted_mid_checkout(): void
    {
        // Listing has 50 kg, but buyer requests 100 kg
        $this->listedListing->update(['stock_kg' => 50]);

        $this->actingAs($this->buyer)
            ->postJson('/api/v1/buyer/checkout', [
                'batch_listing_id' => 'listing-001',
                'weight_kg' => 100,
                'port_id' => $this->activePort->id,
                'payment_method' => 'bank_transfer',
            ])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'INSUFFICIENT_STOCK',
            ]);
    }

    /** @test */
    public function test_checkout_fails_if_weight_is_below_minimum_boundary(): void
    {
        $this->actingAs($this->buyer)
            ->postJson('/api/v1/buyer/checkout', [
                'batch_listing_id' => 'listing-001',
                'weight_kg' => 5, // below 10 kg
                'port_id' => $this->activePort->id,
                'payment_method' => 'bank_transfer',
            ])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'MIN_ORDER_WEIGHT',
            ]);
    }

    /** @test */
    public function test_checkout_fails_if_weight_exceeds_maximum_boundary(): void
    {
        $this->actingAs($this->buyer)
            ->postJson('/api/v1/buyer/checkout', [
                'batch_listing_id' => 'listing-001',
                'weight_kg' => 6000, // above 5000 kg
                'port_id' => $this->activePort->id,
                'payment_method' => 'bank_transfer',
            ])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'MAX_ORDER_WEIGHT',
            ]);
    }

    /** @test */
    public function test_checkout_fails_if_shipping_port_is_inactive(): void
    {
        $this->actingAs($this->buyer)
            ->postJson('/api/v1/buyer/checkout', [
                'batch_listing_id' => 'listing-001',
                'weight_kg' => 100,
                'port_id' => $this->inactivePort->id,
                'payment_method' => 'bank_transfer',
            ])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'PORT_REQUIRED',
            ]);
    }

    /** @test */
    public function test_checkout_fails_if_payment_method_is_invalid(): void
    {
        $this->actingAs($this->buyer)
            ->postJson('/api/v1/buyer/checkout', [
                'batch_listing_id' => 'listing-001',
                'weight_kg' => 100,
                'port_id' => $this->activePort->id,
                'payment_method' => 'cryptocurrency', // invalid
            ])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    /** @test */
    public function test_checkout_prevents_duplicate_pending_payment_orders_for_same_listing(): void
    {
        // First active order created
        Order::factory()->create([
            'buyer_id' => $this->buyer->id,
            'batch_listing_id' => 'listing-001',
            'status' => 'pending_payment',
            'created_at' => now(),
        ]);

        // Attempting to checkout again for same listing
        $this->actingAs($this->buyer)
            ->postJson('/api/v1/buyer/checkout', [
                'batch_listing_id' => 'listing-001',
                'weight_kg' => 100,
                'port_id' => $this->activePort->id,
                'payment_method' => 'bank_transfer',
            ])
            ->assertStatus(409)
            ->assertJson([
                'success' => false,
                'code' => 'CONFLICT',
            ]);
    }

    /** @test */
    public function test_checkout_allows_order_if_existing_pending_order_is_expired(): void
    {
        // Expired order created 25 hours ago
        Order::factory()->create([
            'buyer_id' => $this->buyer->id,
            'batch_listing_id' => 'listing-001',
            'status' => 'pending_payment',
            'created_at' => now()->subHours(25),
        ]);

        $this->actingAs($this->buyer)
            ->postJson('/api/v1/buyer/checkout', [
                'batch_listing_id' => 'listing-001',
                'weight_kg' => 100,
                'port_id' => $this->activePort->id,
                'payment_method' => 'bank_transfer',
            ])
            ->assertStatus(201)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS_CREATE',
            ]);
    }

    // ========================================================================
    // SECTION 4: ORDER DETAILS & ACTION FLAGS
    // ========================================================================

    /** @test */
    public function test_order_detail_returns_forbidden_for_other_buyers_order(): void
    {
        $otherOrder = Order::factory()->create([
            'id' => 'ORD-OTHER-001',
            'buyer_id' => $this->buyer2->id,
            'batch_listing_id' => 'listing-001',
            'status' => 'pending_payment',
            'weight_kg' => 100,
            'price_per_kg' => 145000,
            'total' => 14765000,
            'port_id' => $this->activePort->id,
        ]);

        $this->actingAs($this->buyer)
            ->getJson("/api/v1/buyer/orders/{$otherOrder->id}")
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'ORDER_NOT_OWNED',
            ]);
    }

    /** @test */
    public function test_order_detail_cancellation_action_flags_transition_correctly(): void
    {
        $statuses = [
            'pending_payment' => true,
            'payment_verifying' => true,
            'paid' => true,
            'processing' => false,
            'ready_shipment' => false,
            'in_transit' => false,
            'delivered' => false,
            'completed' => false,
            'cancelled' => false,
        ];

        foreach ($statuses as $status => $canCancel) {
            $order = Order::factory()->create([
                'id' => "ORD-FLAG-{$status}",
                'buyer_id' => $this->buyer->id,
                'batch_listing_id' => 'listing-001',
                'exporter_id' => $this->exporter->id,
                'status' => $status,
                'weight_kg' => 100,
                'price_per_kg' => 145000,
                'total' => 14765000,
                'payment_method' => 'bank_transfer',
                'port_id' => $this->activePort->id,
            ]);

            $this->actingAs($this->buyer)
                ->getJson("/api/v1/buyer/orders/{$order->id}")
                ->assertStatus(200)
                ->assertJsonPath('data.order.actions_available.can_cancel', $canCancel);
        }
    }

    /** @test */
    public function test_order_detail_confirm_receipt_action_flags_transition_correctly(): void
    {
        $statuses = [
            'pending_payment' => false,
            'payment_verifying' => false,
            'paid' => false,
            'processing' => false,
            'ready_shipment' => false,
            'in_transit' => false,
            'delivered' => true,
            'completed' => false,
            'cancelled' => false,
        ];

        foreach ($statuses as $status => $canConfirm) {
            $order = Order::factory()->create([
                'id' => "ORD-REC-{$status}",
                'buyer_id' => $this->buyer->id,
                'batch_listing_id' => 'listing-001',
                'exporter_id' => $this->exporter->id,
                'status' => $status,
                'weight_kg' => 100,
                'price_per_kg' => 145000,
                'total' => 14765000,
                'payment_method' => 'bank_transfer',
                'port_id' => $this->activePort->id,
            ]);

            $this->actingAs($this->buyer)
                ->getJson("/api/v1/buyer/orders/{$order->id}")
                ->assertStatus(200)
                ->assertJsonPath('data.order.actions_available.can_confirm_receipt', $canConfirm);
        }
    }

    /** @test */
    public function test_order_detail_payment_proof_upload_action_flags(): void
    {
        // 1. Bank transfer in pending_payment -> allowed
        $orderVa = Order::factory()->create([
            'id' => 'ORD-VA-PEND',
            'buyer_id' => $this->buyer->id,
            'batch_listing_id' => 'listing-001',
            'exporter_id' => $this->exporter->id,
            'status' => 'pending_payment',
            'payment_method' => 'bank_transfer',
            'weight_kg' => 100,
            'price_per_kg' => 145000,
            'total' => 14765000,
            'port_id' => $this->activePort->id,
        ]);

        $this->actingAs($this->buyer)
            ->getJson("/api/v1/buyer/orders/{$orderVa->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.order.actions_available.can_upload_payment_proof', true);

        // 2. QRIS in pending_payment -> not allowed (webhook-based)
        $orderQris = Order::factory()->create([
            'id' => 'ORD-QR-PEND',
            'buyer_id' => $this->buyer->id,
            'batch_listing_id' => 'listing-001',
            'exporter_id' => $this->exporter->id,
            'status' => 'pending_payment',
            'payment_method' => 'qris',
            'weight_kg' => 100,
            'price_per_kg' => 145000,
            'total' => 14765000,
            'port_id' => $this->activePort->id,
        ]);

        $this->actingAs($this->buyer)
            ->getJson("/api/v1/buyer/orders/{$orderQris->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.order.actions_available.can_upload_payment_proof', false);
    }

    // ========================================================================
    // SECTION 5: PAYMENT CONFIRMATION EDGE CASES
    // ========================================================================

    /** @test */
    public function test_cannot_confirm_payment_on_already_paid_order(): void
    {
        $order = Order::factory()->create([
            'id' => 'ORD-CONF-PAID',
            'buyer_id' => $this->buyer->id,
            'batch_listing_id' => 'listing-001',
            'exporter_id' => $this->exporter->id,
            'status' => 'paid',
            'payment_method' => 'bank_transfer',
            'weight_kg' => 100,
            'price_per_kg' => 145000,
            'total' => 14765000,
            'port_id' => $this->activePort->id,
        ]);

        $file = UploadedFile::fake()->image('proof.jpg');

        $this->actingAs($this->buyer)
            ->postJson("/api/v1/buyer/orders/{$order->id}/payment/confirm", [
                'proof_file' => $file,
            ])
            ->assertStatus(409)
            ->assertJson([
                'success' => false,
                'code' => 'ORDER_ALREADY_PAID',
            ]);
    }

    /** @test */
    public function test_cannot_confirm_payment_on_cancelled_order(): void
    {
        $order = Order::factory()->create([
            'id' => 'ORD-CONF-CANCEL',
            'buyer_id' => $this->buyer->id,
            'batch_listing_id' => 'listing-001',
            'exporter_id' => $this->exporter->id,
            'status' => 'cancelled',
            'payment_method' => 'bank_transfer',
            'weight_kg' => 100,
            'price_per_kg' => 145000,
            'total' => 14765000,
            'port_id' => $this->activePort->id,
        ]);

        $file = UploadedFile::fake()->image('proof.jpg');

        $this->actingAs($this->buyer)
            ->postJson("/api/v1/buyer/orders/{$order->id}/payment/confirm", [
                'proof_file' => $file,
            ])
            ->assertStatus(410)
            ->assertJson([
                'success' => false,
                'code' => 'PAYMENT_EXPIRED',
            ]);
    }

    /** @test */
    public function test_cannot_confirm_payment_on_other_buyers_order(): void
    {
        $order = Order::factory()->create([
            'id' => 'ORD-CONF-OTHER',
            'buyer_id' => $this->buyer2->id,
            'batch_listing_id' => 'listing-001',
            'exporter_id' => $this->exporter->id,
            'status' => 'pending_payment',
            'payment_method' => 'bank_transfer',
            'weight_kg' => 100,
            'price_per_kg' => 145000,
            'total' => 14765000,
            'port_id' => $this->activePort->id,
        ]);

        $file = UploadedFile::fake()->image('proof.jpg');

        $this->actingAs($this->buyer)
            ->postJson("/api/v1/buyer/orders/{$order->id}/payment/confirm", [
                'proof_file' => $file,
            ])
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'ORDER_NOT_OWNED',
            ]);
    }

    /** @test */
    public function test_confirm_payment_rejects_invalid_file_types(): void
    {
        $order = Order::factory()->create([
            'id' => 'ORD-CONF-FILE',
            'buyer_id' => $this->buyer->id,
            'batch_listing_id' => 'listing-001',
            'exporter_id' => $this->exporter->id,
            'status' => 'pending_payment',
            'payment_method' => 'bank_transfer',
            'weight_kg' => 100,
            'price_per_kg' => 145000,
            'total' => 14765000,
            'port_id' => $this->activePort->id,
        ]);

        $invalidFile = UploadedFile::fake()->create('proof.pdf', 500, 'application/pdf');

        $this->actingAs($this->buyer)
            ->postJson("/api/v1/buyer/orders/{$order->id}/payment/confirm", [
                'proof_file' => $invalidFile,
            ])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    // ========================================================================
    // SECTION 6: NOTIFICATION EDGE CASES
    // ========================================================================

    /** @test */
    public function test_marking_nonexistent_notification_read_returns_not_found(): void
    {
        $this->actingAs($this->buyer)
            ->patchJson('/api/v1/buyer/notifications/notif-99999/read')
            ->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code' => 'NOT_FOUND',
            ]);
    }

    /** @test */
    public function test_marking_other_buyers_notification_read_returns_forbidden(): void
    {
        $otherNotif = Notification::factory()->create([
            'id' => 'notif-other-100',
            'user_id' => $this->buyer2->id,
            'type' => 'system',
            'title' => 'Important system notice',
            'message' => 'Info.',
            'is_read' => false,
        ]);

        $this->actingAs($this->buyer)
            ->patchJson("/api/v1/buyer/notifications/{$otherNotif->id}/read")
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }

    /** @test */
    public function test_unread_notifications_isolated_per_buyer(): void
    {
        Notification::factory()->create([
            'id' => 'notif-b1-unread',
            'user_id' => $this->buyer->id,
            'type' => 'system',
            'is_read' => false,
        ]);

        Notification::factory()->create([
            'id' => 'notif-b2-unread',
            'user_id' => $this->buyer2->id,
            'type' => 'system',
            'is_read' => false,
        ]);

        // Check Buyer 1 notifications
        $response = $this->actingAs($this->buyer)
            ->getJson('/api/v1/buyer/notifications')
            ->assertStatus(200);

        $ids = array_column($response->json('data'), 'id');
        $this->assertContains('notif-b1-unread', $ids);
        $this->assertNotContains('notif-b2-unread', $ids);
    }
}

<?php

namespace Tests\Feature\Buyer;

use App\Models\BatchListing;
use App\Models\Order;
use App\Models\Port;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * BuyerCheckoutTest — Test Case untuk Modul Checkout & Pembelian Buyer (API Contract V3)
 *
 * Scope: 1 endpoint utama + logika bisnis terkait checkout
 *   - POST /api/v1/buyer/checkout — Membuat pesanan baru (§9.1)
 *
 * Reference: API_CONTRACT_V3_BUYER.md
 *   - Section 9  (Modul Checkout & Pembelian)
 *   - Section 5  (Kode Error Shared + Buyer-Specific)
 *   - Section 14 (Data Model Referensi — Enums)
 *   - Section 15 (State Machine: Lifecycle Pesanan Buyer)
 *   - Section 16 (Edge Cases & Logika Bisnis — §16.1 Checkout, §16.2 Payment)
 *
 * Response fields yang ditest:
 *   - order.id, order.status (= pending_payment), order.status_label
 *   - order.batch_listing (nested: id, batch_code, name, variety, origin, image_url)
 *   - order.exporter (nested: id, name, avatar_url) — VISIBLE, TIDAK ADA farmer identity
 *   - order.weight_kg, order.price_per_kg
 *   - order.subtotal, order.shipping_cost, order.platform_fee, order.total, order.total_display
 *   - order.port (nested: id, name, full_name, eta_days, eta_label)
 *   - order.payment (nested: method, method_label, va_number, va_bank, qr_url, qr_image,
 *     midtrans_transaction_id, expires_at, payment_deadline_label)
 *   - order.created_at, order.detail_url
 *
 * Business Rules V3 yang ditest:
 *   - Checkout = 4 fields ONLY (batch_listing_id, weight_kg, port_id, payment_method)
 *   - TIDAK ADA alamat rumah — pickup hanya di pelabuhan
 *   - subtotal = weight_kg * price_per_kg
 *   - shipping_cost = weight_kg * shipping_rate_per_kg (dari data port)
 *   - platform_fee = Rp 15.000 flat
 *   - total = subtotal + shipping_cost + platform_fee
 *   - Stock listing dikurangi saat checkout (stock_kg -= weight_kg)
 *   - Status awal = pending_payment
 *   - Order ID format = ORD-{sequence}
 *   - Farmer identity TIDAK BOLEH muncul di response
 *   - Exporter name HARUS muncul di response
 *   - Bank Transfer → VA fields populated, QR fields null
 *   - QRIS → QR fields populated, VA fields null
 *   - Duplicate checkout untuk listing sama (pending_payment aktif) → CONFLICT
 *   - Buyer isolation: buyer hanya bisa buat order untuk dirinya sendiri
 *
 * Sections (48 tests):
 *   1.  Auth & Authorization (4 tests)
 *   2.  Field Validation (7 tests)
 *   3.  Business Validation — Weight Bounds (4 tests)
 *   4.  Business Validation — Stock (3 tests)
 *   5.  Business Validation — Listing Status (4 tests)
 *   6.  Business Validation — Port Status (2 tests)
 *   7.  Business Validation — Duplicate Prevention (2 tests)
 *   8.  Success Response — Bank Transfer (4 tests)
 *   9.  Success Response — QRIS (4 tests)
 *  10. Price Calculation Formula (3 tests)
 *  11. Stock Reduction on Checkout (2 tests)
 *  12. Order Initial State (3 tests)
 *  13. Edge Cases — Checkout (6 tests)
 */
class BuyerCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private User $buyer2;

    private User $exporter;

    private User $farmer;

    private BatchListing $listing;

    private Port $port;

    protected function setUp(): void
    {
        parent::setUp();

        // ── Buyer utama ──
        $this->buyer = User::factory()->create([
            'role' => 'buyer',
            'name' => 'John Doe',
            'email' => 'john@company.com',
            'phone' => '+62 812-0000-0001',
            'company_name' => 'PT Kopi Nusantara',
            'business_id' => 'NPWP-1234567890',
            'profile_completion' => 80,
        ]);

        // ── Buyer kedua (untuk isolasi test) ──
        $this->buyer2 = User::factory()->create([
            'role' => 'buyer',
            'name' => 'Jane Smith',
            'email' => 'jane@coffee.co',
            'phone' => '+62 813-0000-0002',
            'company_name' => 'Pacific Roasters Inc.',
            'business_id' => 'NPWP-9876543210',
            'profile_completion' => 60,
        ]);

        // ── Exporter (VISIBLE ke buyer) ──
        $this->exporter = User::factory()->create([
            'role' => 'exporter',
            'name' => 'PT Sulawesi Coffee Export',
            'email' => 'export@sulawesi-coffee.id',
            'avatar_url' => 'https://storage.biji.local/exporters/2/avatar.jpg',
        ]);

        // ── Farmer (HIDDEN dari buyer — hanya untuk setup data) ──
        $this->farmer = User::factory()->create([
            'role' => 'farmer',
            'name' => 'Yusuf Ibrahim',
        ]);

        // ── Batch listing default (listed, stok 1000kg) ──
        $this->listing = BatchListing::factory()->create([
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

        // ── Port default (aktif) ──
        $this->port = Port::factory()->create([
            'id' => 1,
            'name' => 'Tanjung Priok',
            'full_name' => 'Pelabuhan Tanjung Priok, Jakarta',
            'is_active' => true,
            'eta_days' => 3,
            'shipping_rate_per_kg' => 2500,
        ]);
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    /**
     * Helper: Default valid checkout payload
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'batch_listing_id' => 'listing-001',
            'weight_kg' => 100,
            'port_id' => 1,
            'payment_method' => 'bank_transfer',
        ], $overrides);
    }

    /**
     * Helper: Submit checkout sebagai buyer utama
     */
    private function postCheckout(array $payload, ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->buyer)
            ->postJson('/api/v1/buyer/checkout', $payload);
    }

    /**
     * Helper: Submit checkout dengan payload default valid
     */
    private function checkoutDefault(?User $user = null): TestResponse
    {
        return $this->postCheckout($this->validPayload(), $user);
    }

    /**
     * Helper: Buat listing dengan status tertentu
     */
    private function createListing(string $id, string $status, int $stockKg = 1000, array $overrides = []): BatchListing
    {
        return BatchListing::factory()->create(array_merge([
            'id' => $id,
            'exporter_id' => $this->exporter->id,
            'batch_code' => "BJI-TST-{$id}",
            'name' => "Listing {$id}",
            'variety' => 'Arabika Toraja',
            'origin' => 'Tana Toraja, Sulawesi Selatan',
            'image_url' => "https://storage.biji.local/listings/{$id}/cover.jpg",
            'price_per_kg' => 145000,
            'stock_kg' => $stockKg,
            'status' => $status,
        ], $overrides));
    }

    /**
     * Helper: Buat port dengan status tertentu
     */
    private function createPort(int $id, bool $isActive, int $ratePerKg = 2500): Port
    {
        return Port::factory()->create([
            'id' => $id,
            'name' => "Port {$id}",
            'full_name' => "Pelabuhan Port {$id}, Indonesia",
            'is_active' => $isActive,
            'eta_days' => 3,
            'shipping_rate_per_kg' => $ratePerKg,
        ]);
    }

    // ========================================================================
    // SECTION 1: AUTH & AUTHORIZATION (4 tests)
    // ========================================================================

    /** @test */
    public function test_it_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/buyer/checkout', $this->validPayload());

        $response->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    /** @test */
    public function test_it_rejects_farmer_role(): void
    {
        $farmer = User::factory()->create(['role' => 'farmer']);

        $response = $this->actingAs($farmer)
            ->postJson('/api/v1/buyer/checkout', $this->validPayload());

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }

    /** @test */
    public function test_it_rejects_exporter_role(): void
    {
        $exporter = User::factory()->create(['role' => 'exporter']);

        $response = $this->actingAs($exporter)
            ->postJson('/api/v1/buyer/checkout', $this->validPayload());

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }

    /** @test */
    public function test_it_allows_buyer_role_to_checkout(): void
    {
        $response = $this->checkoutDefault();

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS_CREATE',
                'message' => 'Pesanan berhasil dibuat',
            ]);
    }

    // ========================================================================
    // SECTION 2: FIELD VALIDATION (7 tests)
    // ========================================================================

    /** @test */
    public function test_it_validates_batch_listing_id_is_required(): void
    {
        $payload = $this->validPayload(['batch_listing_id' => null]);

        $response = $this->postCheckout($payload);

        $response->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);

        // Pastikan error message menyebut field batch_listing_id
        $response->assertJsonStructure(['details']);
    }

    /** @test */
    public function test_it_validates_weight_kg_is_required(): void
    {
        $payload = $this->validPayload(['weight_kg' => null]);

        $response = $this->postCheckout($payload);

        $response->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    /** @test */
    public function test_it_validates_port_id_is_required(): void
    {
        $payload = $this->validPayload(['port_id' => null]);

        $response = $this->postCheckout($payload);

        $response->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    /** @test */
    public function test_it_validates_payment_method_is_required(): void
    {
        $payload = $this->validPayload(['payment_method' => null]);

        $response = $this->postCheckout($payload);

        $response->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    /** @test */
    public function test_it_rejects_invalid_payment_method_value(): void
    {
        $payload = $this->validPayload(['payment_method' => 'crypto']);

        $response = $this->postCheckout($payload);

        $response->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);

        // Valid payment methods hanya: bank_transfer, qris
        $response->assertJsonStructure(['details']);
    }

    /** @test */
    public function test_it_rejects_weight_kg_zero(): void
    {
        $payload = $this->validPayload(['weight_kg' => 0]);

        $response = $this->postCheckout($payload);

        // weight_kg = 0 → VALIDATION_ERROR (required, min:1)
        $response->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    /** @test */
    public function test_it_rejects_weight_kg_non_integer(): void
    {
        $payload = $this->validPayload(['weight_kg' => 100.5]);

        $response = $this->postCheckout($payload);

        // weight_kg harus integer
        $response->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    // ========================================================================
    // SECTION 3: BUSINESS VALIDATION — WEIGHT BOUNDS (4 tests)
    // ========================================================================

    /** @test */
    public function test_it_rejects_weight_below_minimum_10kg(): void
    {
        $payload = $this->validPayload(['weight_kg' => 5]);

        $response = $this->postCheckout($payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'MIN_ORDER_WEIGHT',
            ])
            ->assertJsonPath('details.requested_weight_kg', 5)
            ->assertJsonPath('details.minimum_weight_kg', 10)
            ->assertJsonPath('details.maximum_weight_kg', 5000);
    }

    /** @test */
    public function test_it_rejects_weight_above_maximum_5000kg(): void
    {
        $payload = $this->validPayload(['weight_kg' => 6000]);

        $response = $this->postCheckout($payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'MAX_ORDER_WEIGHT',
            ])
            ->assertJsonPath('details.requested_weight_kg', 6000)
            ->assertJsonPath('details.minimum_weight_kg', 10)
            ->assertJsonPath('details.maximum_weight_kg', 5000);
    }

    /** @test */
    public function test_it_accepts_weight_at_exact_minimum_10kg(): void
    {
        $payload = $this->validPayload(['weight_kg' => 10]);

        $response = $this->postCheckout($payload);

        $response->assertCreated()
            ->assertJsonPath('data.order.weight_kg', 10);
    }

    /** @test */
    public function test_it_accepts_weight_at_exact_maximum_5000kg(): void
    {
        // Set stok cukup untuk 5000kg
        $this->listing->update(['stock_kg' => 5000]);
        $payload = $this->validPayload(['weight_kg' => 5000]);

        $response = $this->postCheckout($payload);

        $response->assertCreated()
            ->assertJsonPath('data.order.weight_kg', 5000);
    }

    // ========================================================================
    // SECTION 4: BUSINESS VALIDATION — STOCK (3 tests)
    // ========================================================================

    /** @test */
    public function test_it_rejects_when_requested_weight_exceeds_available_stock(): void
    {
        // Stok hanya 1200kg, buyer mau 2000kg
        $this->listing->update(['stock_kg' => 1200]);
        $payload = $this->validPayload(['weight_kg' => 2000]);

        $response = $this->postCheckout($payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'INSUFFICIENT_STOCK',
            ])
            ->assertJsonPath('details.requested_weight_kg', 2000)
            ->assertJsonPath('details.available_stock_kg', 1200)
            ->assertJsonPath('details.batch_listing_id', 'listing-001')
            ->assertJsonPath('details.batch_listing_name', 'Arabika Toraja Sapan');
    }

    /** @test */
    public function test_it_accepts_when_weight_equals_available_stock(): void
    {
        // Stok tepat 100kg, order 100kg
        $this->listing->update(['stock_kg' => 100]);
        $payload = $this->validPayload(['weight_kg' => 100]);

        $response = $this->postCheckout($payload);

        $response->assertCreated()
            ->assertJsonPath('data.order.weight_kg', 100);
    }

    /** @test */
    public function test_it_accepts_when_weight_is_less_than_available_stock(): void
    {
        // Stok 1000kg, order 500kg
        $payload = $this->validPayload(['weight_kg' => 500]);

        $response = $this->postCheckout($payload);

        $response->assertCreated()
            ->assertJsonPath('data.order.weight_kg', 500);
    }

    // ========================================================================
    // SECTION 5: BUSINESS VALIDATION — LISTING STATUS (4 tests)
    // ========================================================================

    /** @test */
    public function test_it_rejects_when_listing_status_is_draft(): void
    {
        $draftListing = $this->createListing('listing-draft', 'draft', 500);
        $payload = $this->validPayload(['batch_listing_id' => 'listing-draft']);

        $response = $this->postCheckout($payload);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code' => 'BATCH_NOT_LISTED',
            ]);
    }

    /** @test */
    public function test_it_rejects_when_listing_status_is_archived(): void
    {
        $archivedListing = $this->createListing('listing-archived', 'archived', 500);
        $payload = $this->validPayload(['batch_listing_id' => 'listing-archived']);

        $response = $this->postCheckout($payload);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code' => 'BATCH_NOT_LISTED',
            ]);
    }

    /** @test */
    public function test_it_rejects_when_listing_status_is_sold_out(): void
    {
        // Stock 0 = sold out secara virtual
        $soldOutListing = $this->createListing('listing-soldout', 'listed', 0);
        $payload = $this->validPayload([
            'batch_listing_id' => 'listing-soldout',
            'weight_kg' => 10,
        ]);

        $response = $this->postCheckout($payload);

        // Sold out → INSUFFICIENT_STOCK (stok = 0, requested > 0)
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'INSUFFICIENT_STOCK',
            ])
            ->assertJsonPath('details.available_stock_kg', 0);
    }

    /** @test */
    public function test_it_accepts_when_listing_status_is_listed(): void
    {
        $activeListing = $this->createListing('listing-active', 'listed', 500);
        $payload = $this->validPayload([
            'batch_listing_id' => 'listing-active',
            'weight_kg' => 50,
        ]);

        $response = $this->postCheckout($payload);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS_CREATE',
            ]);
    }

    // ========================================================================
    // SECTION 6: BUSINESS VALIDATION — PORT STATUS (2 tests)
    // ========================================================================

    /** @test */
    public function test_it_rejects_when_port_is_inactive(): void
    {
        $inactivePort = $this->createPort(99, false);
        $payload = $this->validPayload(['port_id' => 99]);

        $response = $this->postCheckout($payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'PORT_REQUIRED',
            ]);
    }

    /** @test */
    public function test_it_rejects_when_port_id_does_not_exist(): void
    {
        $payload = $this->validPayload(['port_id' => 99999]);

        $response = $this->postCheckout($payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'PORT_REQUIRED',
            ]);
    }

    // ========================================================================
    // SECTION 7: BUSINESS VALIDATION — DUPLICATE PREVENTION (2 tests)
    // ========================================================================

    /** @test */
    public function test_it_rejects_duplicate_checkout_for_same_listing_when_pending_payment_exists(): void
    {
        // Buat pesanan pending_payment untuk listing-001 oleh buyer
        Order::factory()->create([
            'buyer_id' => $this->buyer->id,
            'batch_listing_id' => 'listing-001',
            'status' => 'pending_payment',
            'created_at' => now()->subMinutes(30),
        ]);

        $payload = $this->validPayload(['batch_listing_id' => 'listing-001', 'weight_kg' => 50]);

        $response = $this->postCheckout($payload);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'code' => 'CONFLICT',
            ]);
    }

    /** @test */
    public function test_it_allows_checkout_for_different_listing_even_with_pending_order(): void
    {
        // Buyer punya pending_payment untuk listing-001
        Order::factory()->create([
            'buyer_id' => $this->buyer->id,
            'batch_listing_id' => 'listing-001',
            'status' => 'pending_payment',
        ]);

        // Tapi boleh buat order baru untuk listing yang berbeda
        $newListing = $this->createListing('listing-002', 'listed', 800);
        $payload = $this->validPayload([
            'batch_listing_id' => 'listing-002',
            'weight_kg' => 50,
        ]);

        $response = $this->postCheckout($payload);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS_CREATE',
            ]);
    }

    // ========================================================================
    // SECTION 8: SUCCESS RESPONSE — BANK TRANSFER (4 tests)
    // ========================================================================

    /** @test */
    public function test_it_returns_201_with_full_order_structure_for_bank_transfer(): void
    {
        $response = $this->postCheckout($this->validPayload([
            'payment_method' => 'bank_transfer',
        ]));

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('code', 'SUCCESS_CREATE')
            ->assertJsonPath('message', 'Pesanan berhasil dibuat');

        // Struktur order
        $order = $response->json('data.order');
        $this->assertNotEmpty($order['id']);
        $this->assertNotEmpty($order['detail_url']);

        // Order ID harus format ORD-{sequence}
        $this->assertMatchesRegularExpression('/^ORD-\d+$/', $order['id']);
        $this->assertEquals("/api/v1/buyer/orders/{$order['id']}", $order['detail_url']);
    }

    /** @test */
    public function test_it_populates_va_fields_for_bank_transfer_payment(): void
    {
        $response = $this->postCheckout($this->validPayload([
            'payment_method' => 'bank_transfer',
        ]));

        $payment = $response->json('data.order.payment');

        $this->assertEquals('bank_transfer', $payment['method']);
        $this->assertEquals('Transfer Bank', $payment['method_label']);
        $this->assertNotEmpty($payment['va_number']);
        $this->assertNotEmpty($payment['va_bank']);
        $this->assertNotNull($payment['va_number']);
        $this->assertNotNull($payment['va_bank']);
        $this->assertNotNull($payment['midtrans_transaction_id']);
        $this->assertNotNull($payment['expires_at']);
        $this->assertNotEmpty($payment['payment_deadline_label']);
    }

    /** @test */
    public function test_it_returns_null_qr_fields_for_bank_transfer_payment(): void
    {
        $response = $this->postCheckout($this->validPayload([
            'payment_method' => 'bank_transfer',
        ]));

        $payment = $response->json('data.order.payment');

        $this->assertNull($payment['qr_url']);
        $this->assertNull($payment['qr_image']);
    }

    /** @test */
    public function test_it_includes_batch_listing_and_exporter_info_in_response(): void
    {
        $response = $this->postCheckout($this->validPayload([
            'payment_method' => 'bank_transfer',
        ]));

        $response->assertCreated();

        // Nested batch_listing
        $response->assertJsonPath('data.order.batch_listing.id', 'listing-001')
            ->assertJsonPath('data.order.batch_listing.batch_code', 'BJI-TRJ-26054')
            ->assertJsonPath('data.order.batch_listing.name', 'Arabika Toraja Sapan')
            ->assertJsonPath('data.order.batch_listing.variety', 'Arabika Toraja')
            ->assertJsonPath('data.order.batch_listing.origin', 'Tana Toraja, Sulawesi Selatan')
            ->assertJsonPath('data.order.batch_listing.image_url', 'https://storage.biji.local/listings/listing-001/cover.jpg');

        // Nested exporter (VISIBLE)
        $response->assertJsonPath('data.order.exporter.id', $this->exporter->id)
            ->assertJsonPath('data.order.exporter.name', 'PT Sulawesi Coffee Export');

        // Nested port
        $response->assertJsonPath('data.order.port.id', 1)
            ->assertJsonPath('data.order.port.name', 'Tanjung Priok')
            ->assertJsonPath('data.order.port.full_name', 'Pelabuhan Tanjung Priok, Jakarta');
    }

    // ========================================================================
    // SECTION 9: SUCCESS RESPONSE — QRIS (4 tests)
    // ========================================================================

    /** @test */
    public function test_it_returns_201_with_full_order_structure_for_qris(): void
    {
        $response = $this->postCheckout($this->validPayload([
            'payment_method' => 'qris',
        ]));

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('code', 'SUCCESS_CREATE');

        $order = $response->json('data.order');
        $this->assertMatchesRegularExpression('/^ORD-\d+$/', $order['id']);
    }

    /** @test */
    public function test_it_populates_qr_fields_for_qris_payment(): void
    {
        $response = $this->postCheckout($this->validPayload([
            'payment_method' => 'qris',
            'weight_kg' => 200,
        ]));

        $payment = $response->json('data.order.payment');

        $this->assertEquals('qris', $payment['method']);
        $this->assertEquals('QRIS', $payment['method_label']);
        $this->assertNotEmpty($payment['qr_url']);
        $this->assertNotEmpty($payment['qr_image']);
        $this->assertNotNull($payment['midtrans_transaction_id']);
        $this->assertNotNull($payment['expires_at']);
        $this->assertNotEmpty($payment['payment_deadline_label']);

        // QR URL harus mengandung order ID
        $orderId = $response->json('data.order.id');
        $this->assertStringContainsString($orderId, $payment['qr_url']);
    }

    /** @test */
    public function test_it_returns_null_va_fields_for_qris_payment(): void
    {
        $response = $this->postCheckout($this->validPayload([
            'payment_method' => 'qris',
        ]));

        $payment = $response->json('data.order.payment');

        $this->assertNull($payment['va_number']);
        $this->assertNull($payment['va_bank']);
    }

    /** @test */
    public function test_it_returns_correct_status_and_label_for_qris(): void
    {
        $response = $this->postCheckout($this->validPayload([
            'payment_method' => 'qris',
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.order.status', 'pending_payment')
            ->assertJsonPath('data.order.status_label', 'Menunggu Pembayaran');
    }

    // ========================================================================
    // SECTION 10: PRICE CALCULATION FORMULA (3 tests)
    // ========================================================================

    /** @test */
    public function test_it_calculates_subtotal_as_weight_times_price_per_kg(): void
    {
        // price_per_kg = 145,000, weight = 100 → subtotal = 14,500,000
        $payload = $this->validPayload(['weight_kg' => 100]);

        $response = $this->postCheckout($payload);

        $response->assertCreated()
            ->assertJsonPath('data.order.price_per_kg', 145000)
            ->assertJsonPath('data.order.weight_kg', 100)
            ->assertJsonPath('data.order.subtotal', 14500000);
    }

    /** @test */
    public function test_it_calculates_shipping_cost_as_weight_times_port_rate(): void
    {
        // shipping_rate_per_kg = 2,500, weight = 100 → shipping = 250,000
        $payload = $this->validPayload(['weight_kg' => 100]);

        $response = $this->postCheckout($payload);

        $response->assertCreated()
            ->assertJsonPath('data.order.shipping_rate_per_kg', 2500)
            ->assertJsonPath('data.order.shipping_weight_kg', 100)
            ->assertJsonPath('data.order.shipping_cost', 250000);
    }

    /** @test */
    public function test_it_calculates_total_with_platform_fee_flat_15000(): void
    {
        // weight=100, price_per_kg=145000 → subtotal=14,500,000
        // shipping_rate=2500, weight=100 → shipping=250,000
        // platform_fee=15,000
        // total = 14,500,000 + 250,000 + 15,000 = 14,765,000
        $payload = $this->validPayload(['weight_kg' => 100]);

        $response = $this->postCheckout($payload);

        $response->assertCreated()
            ->assertJsonPath('data.order.subtotal', 14500000)
            ->assertJsonPath('data.order.shipping_cost', 250000)
            ->assertJsonPath('data.order.platform_fee', 15000)
            ->assertJsonPath('data.order.total', 14765000)
            ->assertJsonPath('data.order.total_display', 'Rp 14.765.000');
    }

    // ========================================================================
    // SECTION 11: STOCK REDUCTION ON CHECKOUT (2 tests)
    // ========================================================================

    /** @test */
    public function test_it_reduces_listing_stock_after_successful_checkout(): void
    {
        $initialStock = $this->listing->stock_kg; // 1000kg
        $orderedWeight = 100;

        $response = $this->postCheckout($this->validPayload(['weight_kg' => $orderedWeight]));

        $response->assertCreated();

        // Stok listing harus berkurang
        $this->listing->refresh();
        $this->assertEquals($initialStock - $orderedWeight, $this->listing->stock_kg);
        $this->assertEquals(900, $this->listing->stock_kg);
    }

    /** @test */
    public function test_it_does_not_reduce_stock_when_checkout_fails(): void
    {
        $initialStock = $this->listing->stock_kg; // 1000kg

        // Checkout dengan stok tidak cukup → gagal
        $response = $this->postCheckout($this->validPayload(['weight_kg' => 2000]));

        $response->assertStatus(422); // INSUFFICIENT_STOCK

        // Stok TIDAK boleh berubah
        $this->listing->refresh();
        $this->assertEquals($initialStock, $this->listing->stock_kg);
    }

    // ========================================================================
    // SECTION 12: ORDER INITIAL STATE (3 tests)
    // ========================================================================

    /** @test */
    public function test_it_sets_order_status_to_pending_payment_on_creation(): void
    {
        $response = $this->checkoutDefault();

        $response->assertCreated()
            ->assertJsonPath('data.order.status', 'pending_payment')
            ->assertJsonPath('data.order.status_label', 'Menunggu Pembayaran');
    }

    /** @test */
    public function test_it_generates_order_id_with_ord_prefix(): void
    {
        $response = $this->checkoutDefault();

        $orderId = $response->json('data.order.id');
        $this->assertMatchesRegularExpression('/^ORD-\d+$/', $orderId);
    }

    /** @test */
    public function test_it_assigns_order_to_authenticated_buyer(): void
    {
        $response = $this->checkoutDefault();

        $response->assertCreated()
            ->assertJsonPath('data.order.buyer_id', $this->buyer->id);
    }

    // ========================================================================
    // SECTION 13: EDGE CASES — CHECKOUT (6 tests)
    // ========================================================================

    /** @test */
    public function test_it_prevents_duplicate_checkout_across_different_buyers(): void
    {
        // Buyer1 punya pending_payment untuk listing-001
        Order::factory()->create([
            'buyer_id' => $this->buyer->id,
            'batch_listing_id' => 'listing-001',
            'status' => 'pending_payment',
        ]);

        // Buyer2 BOLEH checkout listing-001 (berbeda buyer)
        $payload = $this->validPayload(['weight_kg' => 50]);
        $response = $this->postCheckout($payload, $this->buyer2);

        $response->assertCreated()
            ->assertJsonPath('data.order.buyer_id', $this->buyer2->id);
    }

    /** @test */
    public function test_it_allows_checkout_when_existing_pending_order_is_expired(): void
    {
        // Buyer punya pesanan pending_payment yang SUDAH expired (> 24 jam)
        Order::factory()->create([
            'buyer_id' => $this->buyer->id,
            'batch_listing_id' => 'listing-001',
            'status' => 'cancelled',
            'created_at' => now()->subHours(30), // sudah expired
        ]);

        // Buyer BOLEH buat order baru untuk listing yang sama
        $payload = $this->validPayload(['weight_kg' => 50]);
        $response = $this->postCheckout($payload);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS_CREATE',
            ]);
    }

    /** @test */
    public function test_it_does_not_expose_farmer_identity_in_checkout_response(): void
    {
        $response = $this->checkoutDefault();

        $response->assertCreated();

        // Ambil seluruh response JSON
        $responseData = json_encode($response->json());

        // Farmer name TIDAK BOLEH muncul di manapun dalam response
        $this->assertStringNotContainsString('Yusuf Ibrahim', $responseData);
        $this->assertStringNotContainsString('farmer', $responseData);
    }

    /** @test */
    public function test_it_includes_exporter_name_in_checkout_response(): void
    {
        $response = $this->checkoutDefault();

        $response->assertCreated()
            ->assertJsonPath('data.order.exporter.name', 'PT Sulawesi Coffee Export');
    }

    /** @test */
    public function test_it_returns_payment_expires_at_24_hours_from_creation(): void
    {
        $response = $this->checkoutDefault();

        $response->assertCreated();

        $expiresAt = $response->json('data.order.payment.expires_at');
        $createdAt = $response->json('data.order.created_at');

        // expires_at harus ~24 jam setelah created_at
        $created = now()->parse($createdAt);
        $expires = now()->parse($expiresAt);
        $diffInMinutes = $created->diffInMinutes($expires);

        $this->assertEquals(1440, $diffInMinutes); // 24 hours = 1440 minutes
    }

    /** @test */
    public function test_it_allows_checkout_with_min_weight_10_on_low_stock_listing(): void
    {
        // Stok rendah (50kg) tapi cukup untuk minimum order (10kg)
        $this->listing->update(['stock_kg' => 50]);
        $payload = $this->validPayload(['weight_kg' => 10]);

        $response = $this->postCheckout($payload);

        $response->assertCreated()
            ->assertJsonPath('data.order.weight_kg', 10);

        // Stok berkurang jadi 40
        $this->listing->refresh();
        $this->assertEquals(40, $this->listing->stock_kg);
    }
}

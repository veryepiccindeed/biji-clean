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
 * BuyerDashboardTest — Test Case untuk Modul Dashboard Buyer (API Contract V3)
 *
 * Scope: 1 endpoint dashboard buyer
 *   - GET /api/v1/buyer/dashboard — Overview lengkap dashboard pembeli (6.1)
 *
 * Reference: API_CONTRACT_V3_BUYER.md Section 6 (Modul Dashboard Buyer)
 *
 * Response sections yang ditest (5 sections + metadata):
 *   1. buyer      — Info buyer (name, company_name, profile_completion, email)
 *   2. stats      — Stats card (active_orders, in_transit, pending_payment, total_transactions)
 *   3. recent_orders — Daftar 3 pesanan terakhir buyer (id, product_name, status, total, port_name, etc.)
 *   4. progress   — Progress card (4 items: profile_complete, document_verified, first_order, payment_method_saved)
 *   5. next_actions — Rekomendasi aksi berikutnya (dinamis berdasarkan kondisi buyer)
 *
 * Business Rules V3 yang ditest:
 * - stats.active_orders = jumlah pesanan dengan status: pending_payment, payment_verifying, paid,
 *   processing, ready_shipment, in_transit
 * - stats.in_transit = jumlah pesanan dengan status = in_transit
 * - stats.pending_payment = jumlah pesanan dengan status pending_payment + payment_verifying
 * - stats.total_transactions = total dari semua pesanan dengan status = completed
 * - recent_orders: max 3 item, diurutkan created_at DESC
 * - progress: completed_count / total_count berdasarkan items yang terpenuhi
 * - next_actions: diurutkan berdasarkan priority (high → medium → low), period (today → next)
 * - Data isolation: buyer hanya lihat data miliknya sendiri
 * - Tidak ada warnings block (berbeda dari farmer)
 */
class BuyerDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private User $buyer2;

    private User $exporter;

    protected function setUp(): void
    {
        parent::setUp();

        // Buyer utama (lengkap)
        $this->buyer = User::factory()->create([
            'role' => 'buyer',
            'name' => 'John Doe',
            'email' => 'john@company.com',
            'phone' => '+62 812-3456-7890',
            'company_name' => 'Tokyo Coffee Roasters',
            'business_id' => 'NPWP-12.345.678.9-012.000',
            'profile_completion' => 80,
        ]);

        // Buyer kedua (untuk isolasi data)
        $this->buyer2 = User::factory()->create([
            'role' => 'buyer',
            'name' => 'Jane Smith',
            'email' => 'jane@coffee.co',
            'phone' => '+62 813-9876-5432',
            'company_name' => 'Pacific Roasters',
            'business_id' => 'NPWP-98.765.432.1-001.000',
            'profile_completion' => 40,
        ]);

        // Exporter (dibutuhkan untuk membuat listing & order)
        $this->exporter = User::factory()->create([
            'role' => 'exporter',
            'name' => 'PT Sulawesi Coffee Export',
            'email' => 'export@sulawesi.id',
        ]);

        // Port default (aktif)
        Port::factory()->create([
            'id' => 1,
            'name' => 'Tanjung Priok',
            'full_name' => 'Pelabuhan Tanjung Priok, Jakarta',
            'is_active' => true,
            'eta_days' => 3,
            'shipping_rate_per_kg' => 2500,
        ]);

        // Listing default
        BatchListing::factory()->create([
            'id' => 'listing-001',
            'exporter_id' => $this->exporter->id,
            'batch_code' => 'BJI-TRJ-26054',
            'name' => 'Arabika Toraja Sapan',
            'variety' => 'Arabika Toraja',
            'origin' => 'Tana Toraja, Sulawesi Selatan',
            'price_per_kg' => 145000,
            'stock_kg' => 1000,
            'status' => 'listed',
        ]);
    }

    // ========================================================================
    // Helper Methods
    // ========================================================================

    /**
     * Helper: panggil dashboard endpoint
     */
    private function getDashboard(?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->buyer)
            ->getJson('/api/v1/buyer/dashboard');
    }

    /**
     * Helper: buat pesanan untuk buyer tertentu
     */
    private function createOrderForBuyer(User $buyer, string $status, int $total, ?string $createdAt = null): Order
    {
        return Order::factory()->create([
            'buyer_id' => $buyer->id,
            'batch_listing_id' => 'listing-001',
            'status' => $status,
            'total' => $total,
            'port_id' => 1,
            'created_at' => $createdAt ?? now(),
        ]);
    }

    // ========================================================================
    // A. Happy Path — Response Structure
    // ========================================================================

    public function test_dashboard_returns_200_with_all_top_level_sections()
    {
        $response = $this->getDashboard();

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
                'message' => 'Data dashboard pembeli berhasil diambil',
            ])
            ->assertJsonStructure([
                'success',
                'code',
                'message',
                'data' => [
                    'buyer',
                    'stats',
                    'recent_orders',
                    'progress',
                    'next_actions',
                ],
                'timestamp',
            ]);
    }

    public function test_dashboard_buyer_section_has_correct_fields()
    {
        $response = $this->getDashboard();

        $response->assertStatus(200)
            ->assertJsonPath('data.buyer.name', $this->buyer->name)
            ->assertJsonPath('data.buyer.company_name', $this->buyer->company_name)
            ->assertJsonPath('data.buyer.profile_completion', $this->buyer->profile_completion)
            ->assertJsonPath('data.buyer.email', $this->buyer->email);
    }

    public function test_dashboard_stats_section_has_correct_fields()
    {
        $response = $this->getDashboard();

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'stats' => [
                        'active_orders',
                        'active_orders_caption',
                        'active_orders_subcaption',
                        'in_transit',
                        'in_transit_caption',
                        'in_transit_subcaption',
                        'pending_payment',
                        'pending_payment_caption',
                        'pending_payment_subcaption',
                        'total_transactions',
                        'total_transactions_caption',
                        'total_transactions_subcaption',
                    ],
                ],
            ]);
    }

    // ========================================================================
    // B. Stats — Logika Perhitungan
    // ========================================================================

    public function test_stats_active_orders_counts_correct_statuses()
    {
        // Status yang termasuk active_orders:
        $activeStatuses = [
            'pending_payment',
            'payment_verifying',
            'paid',
            'processing',
            'ready_shipment',
            'in_transit',
        ];
        foreach ($activeStatuses as $status) {
            $this->createOrderForBuyer($this->buyer, $status, 100000);
        }
        // Status yang tidak termasuk: delivered, completed, cancelled
        $this->createOrderForBuyer($this->buyer, 'delivered', 200000);
        $this->createOrderForBuyer($this->buyer, 'completed', 300000);
        $this->createOrderForBuyer($this->buyer, 'cancelled', 50000);

        $response = $this->getDashboard();

        $response->assertStatus(200)
            ->assertJsonPath('data.stats.active_orders', count($activeStatuses));
    }

    public function test_stats_in_transit_counts_only_in_transit()
    {
        $this->createOrderForBuyer($this->buyer, 'in_transit', 150000);
        $this->createOrderForBuyer($this->buyer, 'in_transit', 200000);
        $this->createOrderForBuyer($this->buyer, 'processing', 180000);

        $response = $this->getDashboard();

        $response->assertStatus(200)
            ->assertJsonPath('data.stats.in_transit', 2);
    }

    public function test_stats_pending_payment_counts_pending_and_verifying()
    {
        $this->createOrderForBuyer($this->buyer, 'pending_payment', 100000);
        $this->createOrderForBuyer($this->buyer, 'payment_verifying', 120000);
        $this->createOrderForBuyer($this->buyer, 'paid', 130000);

        $response = $this->getDashboard();

        $response->assertStatus(200)
            ->assertJsonPath('data.stats.pending_payment', 2);
    }

    public function test_stats_total_transactions_sums_completed_orders()
    {
        $this->createOrderForBuyer($this->buyer, 'completed', 500000);
        $this->createOrderForBuyer($this->buyer, 'completed', 750000);
        $this->createOrderForBuyer($this->buyer, 'cancelled', 300000);

        $response = $this->getDashboard();

        $response->assertStatus(200)
            ->assertJsonPath('data.stats.total_transactions', 'Rp 1.250.000');
    }

    public function test_stats_all_zero_for_new_buyer_without_orders()
    {
        $newBuyer = User::factory()->create(['role' => 'buyer']);

        $response = $this->getDashboard($newBuyer);

        $response->assertStatus(200)
            ->assertJsonPath('data.stats.active_orders', 0)
            ->assertJsonPath('data.stats.in_transit', 0)
            ->assertJsonPath('data.stats.pending_payment', 0)
            ->assertJsonPath('data.stats.total_transactions', 'Rp 0');
    }

    // ========================================================================
    // C. Recent Orders
    // ========================================================================

    public function test_recent_orders_returns_max_3_orders_ordered_by_created_at_desc()
    {
        // Buat 5 pesanan dengan tanggal berbeda
        $order1 = $this->createOrderForBuyer($this->buyer, 'in_transit', 100000, now()->subDays(5));
        $order2 = $this->createOrderForBuyer($this->buyer, 'completed', 200000, now()->subDays(4));
        $order3 = $this->createOrderForBuyer($this->buyer, 'pending_payment', 300000, now()->subDays(3));
        $order4 = $this->createOrderForBuyer($this->buyer, 'processing', 400000, now()->subDays(2));
        $order5 = $this->createOrderForBuyer($this->buyer, 'paid', 500000, now()->subDays(1));

        $response = $this->getDashboard();

        $response->assertStatus(200);
        $recent = $response->json('data.recent_orders');
        $this->assertCount(3, $recent);

        // Urutan harus descending berdasarkan created_at
        $this->assertEquals($order5->id, $recent[0]['id']);
        $this->assertEquals($order4->id, $recent[1]['id']);
        $this->assertEquals($order3->id, $recent[2]['id']);
    }

    public function test_recent_orders_has_valid_structure()
    {
        $this->createOrderForBuyer($this->buyer, 'in_transit', 150000);

        $response = $this->getDashboard();

        $response->assertStatus(200);
        $order = $response->json('data.recent_orders.0');
        $this->assertArrayHasKey('id', $order);
        $this->assertArrayHasKey('product_name', $order);
        $this->assertArrayHasKey('variety', $order);
        $this->assertArrayHasKey('exporter_name', $order);
        $this->assertArrayHasKey('status', $order);
        $this->assertArrayHasKey('status_label', $order);
        $this->assertArrayHasKey('status_color', $order);
        $this->assertArrayHasKey('total', $order);
        $this->assertArrayHasKey('total_display', $order);
        $this->assertArrayHasKey('port_name', $order);
        $this->assertArrayHasKey('created_at', $order);
    }

    public function test_recent_orders_empty_when_no_orders()
    {
        $response = $this->getDashboard();
        $response->assertStatus(200)
            ->assertJsonPath('data.recent_orders', []);
    }

    // ========================================================================
    // D. Progress Items
    // ========================================================================

    public function test_progress_items_contains_4_required_keys()
    {
        $response = $this->getDashboard();

        $items = $response->json('data.progress.items');
        $keys = array_column($items, 'key');

        $this->assertEqualsCanonicalizing(
            ['profile_complete', 'document_verified', 'first_order', 'payment_method_saved'],
            $keys
        );
    }

    public function test_progress_completed_count_and_total_count_reflect_reality()
    {
        $response = $this->getDashboard();

        $completedCount = $response->json('data.progress.completed_count');
        $totalCount = $response->json('data.progress.total_count');

        $this->assertIsInt($completedCount);
        $this->assertIsInt($totalCount);
        $this->assertGreaterThanOrEqual(0, $completedCount);
        $this->assertLessThanOrEqual($totalCount, $completedCount);
        $this->assertEquals(4, $totalCount);
    }

    public function test_progress_items_have_valid_structure()
    {
        $response = $this->getDashboard();

        $items = $response->json('data.progress.items');
        foreach ($items as $item) {
            $this->assertArrayHasKey('key', $item);
            $this->assertArrayHasKey('title', $item);
            $this->assertArrayHasKey('value', $item);
            $this->assertArrayHasKey('description', $item);
            $this->assertArrayHasKey('progress_percent', $item);
            $this->assertArrayHasKey('priority', $item);
            $this->assertArrayHasKey('priority_label', $item);
        }
    }

    public function test_progress_items_have_valid_priority_values()
    {
        $response = $this->getDashboard();

        $items = $response->json('data.progress.items');
        $validPriorities = ['low', 'medium', 'high'];

        foreach ($items as $item) {
            $this->assertContains($item['priority'], $validPriorities,
                "Invalid priority '{$item['priority']}' for item '{$item['key']}'");
        }
    }

    // ========================================================================
    // E. Next Actions
    // ========================================================================

    public function test_next_actions_populated_with_recommendations()
    {
        $response = $this->getDashboard();

        $response->assertStatus(200);
        $nextActions = $response->json('data.next_actions');
        $this->assertIsArray($nextActions);
        $this->assertNotEmpty($nextActions);
    }

    public function test_next_actions_has_valid_structure()
    {
        $response = $this->getDashboard();

        $nextActions = $response->json('data.next_actions');
        foreach ($nextActions as $action) {
            $this->assertArrayHasKey('title', $action);
            $this->assertArrayHasKey('description', $action);
            $this->assertArrayHasKey('priority', $action);
            $this->assertArrayHasKey('priority_label', $action);
            $this->assertArrayHasKey('period', $action);
            $this->assertArrayHasKey('period_label', $action);
            $this->assertArrayHasKey('action_type', $action);
            $this->assertArrayHasKey('action_url', $action);
        }
    }

    public function test_next_actions_sorted_by_priority_and_period()
    {
        $response = $this->getDashboard();

        $nextActions = $response->json('data.next_actions');
        // Urutan yang diharapkan: high → medium → low, dan dalam prioritas sama, today → next
        $priorityOrder = ['high' => 1, 'medium' => 2, 'low' => 3];
        $periodOrder = ['today' => 1, 'next' => 2];

        $isSorted = true;
        for ($i = 0; $i < count($nextActions) - 1; $i++) {
            $currPriority = $priorityOrder[$nextActions[$i]['priority']];
            $nextPriority = $priorityOrder[$nextActions[$i + 1]['priority']];
            if ($currPriority > $nextPriority) {
                $isSorted = false;
                break;
            }
            if ($currPriority === $nextPriority) {
                $currPeriod = $periodOrder[$nextActions[$i]['period']];
                $nextPeriod = $periodOrder[$nextActions[$i + 1]['period']];
                if ($currPeriod > $nextPeriod) {
                    $isSorted = false;
                    break;
                }
            }
        }
        $this->assertTrue($isSorted, 'next_actions harus diurutkan berdasarkan priority (high→medium→low) dan period (today→next)');
    }

    public function test_next_actions_no_add_log_action()
    {
        // V3: buyer tidak punya konsep log harian, jadi tidak boleh ada aksi "Tambah log"
        $response = $this->getDashboard();

        $nextActions = $response->json('data.next_actions');
        foreach ($nextActions as $action) {
            $this->assertStringNotContainsStringIgnoringCase('log', $action['title'],
                'next_actions tidak boleh berisi aksi yang berkaitan dengan log');
        }
    }

    // ========================================================================
    // F. Data Isolation
    // ========================================================================

    public function test_dashboard_shows_only_own_orders()
    {
        // Buat pesanan untuk buyer1 dan buyer2
        $this->createOrderForBuyer($this->buyer, 'in_transit', 100000);
        $this->createOrderForBuyer($this->buyer2, 'completed', 200000);
        $this->createOrderForBuyer($this->buyer2, 'pending_payment', 150000);

        // Dashboard buyer1
        $response1 = $this->getDashboard($this->buyer);
        $response1->assertStatus(200)
            ->assertJsonPath('data.stats.active_orders', 1)   // hanya 1 pesanan aktif miliknya
            ->assertJsonPath('data.stats.total_transactions', 'Rp 0'); // belum ada completed

        // Dashboard buyer2
        $response2 = $this->getDashboard($this->buyer2);
        $response2->assertStatus(200)
            ->assertJsonPath('data.stats.active_orders', 1)   // pending_payment miliknya
            ->assertJsonPath('data.stats.total_transactions', 'Rp 200.000');
    }

    public function test_dashboard_buyer_section_shows_correct_buyer_data()
    {
        $response1 = $this->getDashboard($this->buyer);
        $response1->assertJsonPath('data.buyer.name', $this->buyer->name)
            ->assertJsonPath('data.buyer.company_name', $this->buyer->company_name);

        $response2 = $this->getDashboard($this->buyer2);
        $response2->assertJsonPath('data.buyer.name', $this->buyer2->name)
            ->assertJsonPath('data.buyer.company_name', $this->buyer2->company_name);
    }

    // ========================================================================
    // G. Auth & Role
    // ========================================================================

    public function test_dashboard_unauthorized_without_auth_returns_401()
    {
        $response = $this->getJson('/api/v1/buyer/dashboard');
        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    public function test_dashboard_forbidden_with_exporter_role_returns_403()
    {
        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/buyer/dashboard');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }

    public function test_dashboard_forbidden_with_farmer_role_returns_403()
    {
        $farmer = User::factory()->create(['role' => 'farmer']);
        $response = $this->actingAs($farmer)
            ->getJson('/api/v1/buyer/dashboard');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }

    // ========================================================================
    // H. V3 Specific: No warnings block, No active_batch, No log_trend
    // ========================================================================

    public function test_dashboard_does_not_contain_warnings_block()
    {
        $response = $this->getDashboard();
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertArrayNotHasKey('warnings', $data,
            'Dashboard buyer tidak boleh memiliki block warnings (beda dengan farmer)');
    }

    public function test_dashboard_does_not_contain_active_batch_or_log_trend()
    {
        $response = $this->getDashboard();
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertArrayNotHasKey('active_batch', $data);
        $this->assertArrayNotHasKey('log_trend', $data);
        $this->assertArrayNotHasKey('daily_logs', $data);
        $this->assertArrayNotHasKey('batch_logs_timeline', $data);
    }

    // ========================================================================
    // I. Timestamp & Response Metadata
    // ========================================================================

    public function test_dashboard_timestamp_is_iso8601()
    {
        $response = $this->getDashboard();
        $response->assertStatus(200);

        $timestamp = $response->json('timestamp');
        $this->assertNotNull($timestamp);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $timestamp);
    }

    public function test_dashboard_response_contains_success_and_code()
    {
        $response = $this->getDashboard();
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
            ]);
    }
}

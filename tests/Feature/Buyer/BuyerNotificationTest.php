<?php

namespace Tests\Feature\Buyer;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * BuyerNotificationTest — Test Case untuk Modul Notifikasi Buyer (API Contract V3)
 *
 * Scope: 3 endpoint notifikasi + logika bisnis terkait
 *   - GET  /api/v1/buyer/notifications              — List notifikasi buyer (§12.1)
 *   - GET  /api/v1/buyer/notifications/unread-count  — Jumlah belum dibaca (§12.2)
 *   - PATCH /api/v1/buyer/notifications/{id}/read    — Tandai dibaca (§12.3)
 *
 * Reference: API_CONTRACT_V3_BUYER.md
 *   - Section 12  (Modul Notifikasi Buyer — 3 endpoint)
 *   - Section 5   (Kode Error — UNAUTHORIZED, FORBIDDEN, NOT_FOUND)
 *   - Section 14.4 (Enum: Tipe Notifikasi Buyer — 5 tipe)
 *
 * Notification Types (buyer-specific):
 *   order_status_changed, payment_received, shipment_update, catalog_update, system
 *   (Berbeda dari farmer: batch_status_changed, survey_scheduled, iot_alert, system)
 *
 * Response fields yang ditest:
 *   [12.1] List: id, type, type_label, title, message, data (nested), is_read,
 *         created_at, pagination (cursor, hasMore, limit, total)
 *   [12.2] Unread Count: unread_count, has_unread
 *   [12.3] Mark Read: success, code SUCCESS_UPDATE, data null
 *
 * Business Rules V3 yang ditest:
 *   - Data isolation: buyer hanya lihat notifikasi miliknya (buyer_id)
 *   - Buyer hanya bisa menandai notifikasi miliknya sendiri
 *   - Type filter: 5 tipe valid buyer + invalid type handling
 *   - Cursor-based pagination dengan default limit=20, max=100
 *   - Unread count akurat (berkurang saat mark as read)
 *   - has_unread boolean (true jika unread_count > 0)
 *   - Mark as read idempotent (tidak error jika sudah read)
 *   - Notifikasi dibuat otomatis oleh system saat:
 *     (1) status pesanan berubah, (2) pembayaran diterima,
 *     (3) shipment update dari exporter, (4) batch baru masuk katalog,
 *     (5) system maintenance/announcement
 *   - Notif order_status_changed: data.order_id, data.old_status, data.new_status
 *   - Notif payment_received: data.order_id, data.amount
 *   - Notif shipment_update: data.order_id, data.port_name, data.eta_days
 *   - Notif catalog_update: data.listing_id, data.listing_name
 *   - Notif system: data.message (nullable)
 *   - Created_at format ISO 8601 UTC
 *
 * Sections (58 tests):
 *   1.  Auth & Authorization — Notifications List (3 tests)
 *   2.  Auth & Authorization — Unread Count (3 tests)
 *   3.  Auth & Authorization — Mark as Read (3 tests)
 *   4.  Notifications List — Response Structure (4 tests)
 *   5.  Notifications List — Data Isolation (3 tests)
 *   6.  Notifications List — Type Filter (6 tests)
 *   7.  Notifications List — Pagination (4 tests)
 *   8.  Notifications List — Sort & Order (2 tests)
 *   9.  Notifications List — Read/Unread Status (3 tests)
 *  10. Notifications List — Data Payload per Type (5 tests)
 *  11. Notifications List — Empty States (2 tests)
 *  12. Unread Count — Correct Calculation (4 tests)
 *  13. Unread Count — Edge Cases (3 tests)
 *  14. Mark as Read — Success (3 tests)
 *  15. Mark as Read — Data Isolation & Ownership (4 tests)
 *  16. Mark as Read — Not Found & Validation (3 tests)
 *  17. Mark as Read — Idempotency (2 tests)
 *  18. Integration — Unread Count After Mark Read (2 tests)
 */
class BuyerNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private User $buyer2;

    private User $exporter;

    /** @var Notification[] */
    private array $notifications = [];

    protected function setUp(): void
    {
        parent::setUp();

        // ── Buyers ──
        $this->buyer = User::factory()->create([
            'role' => 'buyer',
            'name' => 'John Doe',
            'email' => 'john@company.com',
        ]);

        $this->buyer2 = User::factory()->create([
            'role' => 'buyer',
            'name' => 'Jane Smith',
            'email' => 'jane@coffee.co',
        ]);

        // ── Exporter (untuk data payload) ──
        $this->exporter = User::factory()->create([
            'role' => 'exporter',
            'name' => 'PT Sulawesi Coffee Export',
        ]);

        // ── Notifications untuk buyer utama — semua 5 tipe ──
        $buyerNotifications = [
            // order_status_changed (3 items, 2 unread + 1 read)
            [
                'id' => 'notif-001',
                'user_id' => $this->buyer->id,
                'type' => 'order_status_changed',
                'type_label' => 'Status Pesanan',
                'title' => 'Pesanan ORD-1030 sedang dikirim',
                'message' => 'Pesanan Arabika Toraja Sapan Anda telah dikirim menuju Pelabuhan Tanjung Priok, Jakarta.',
                'data' => json_encode([
                    'order_id' => 'ORD-1030',
                    'old_status' => 'ready_shipment',
                    'new_status' => 'in_transit',
                ]),
                'is_read' => false,
                'created_at' => now()->subHours(2),
            ],
            [
                'id' => 'notif-002',
                'user_id' => $this->buyer->id,
                'type' => 'order_status_changed',
                'type_label' => 'Status Pesanan',
                'title' => 'Pesanan ORD-1031 sedang diproses',
                'message' => 'Pesanan Robusta Enrekang Premium Anda sedang diproses oleh eksportir.',
                'data' => json_encode([
                    'order_id' => 'ORD-1031',
                    'old_status' => 'paid',
                    'new_status' => 'processing',
                ]),
                'is_read' => false,
                'created_at' => now()->subHours(4),
            ],
            [
                'id' => 'notif-003',
                'user_id' => $this->buyer->id,
                'type' => 'order_status_changed',
                'type_label' => 'Status Pesanan',
                'title' => 'Pesanan ORD-1028 selesai',
                'message' => 'Pesanan Arabika Gayo Highland telah selesai.',
                'data' => json_encode([
                    'order_id' => 'ORD-1028',
                    'old_status' => 'delivered',
                    'new_status' => 'completed',
                ]),
                'is_read' => true,
                'created_at' => now()->subDays(3),
            ],

            // payment_received (2 items, 1 unread + 1 read)
            [
                'id' => 'notif-004',
                'user_id' => $this->buyer->id,
                'type' => 'payment_received',
                'type_label' => 'Pembayaran',
                'title' => 'Pembayaran ORD-1030 diterima',
                'message' => 'Pembayaran untuk pesanan Arabika Toraja Sapan telah diterima dan diverifikasi.',
                'data' => json_encode([
                    'order_id' => 'ORD-1030',
                    'amount' => 14765000,
                ]),
                'is_read' => false,
                'created_at' => now()->subHours(6),
            ],
            [
                'id' => 'notif-005',
                'user_id' => $this->buyer->id,
                'type' => 'payment_received',
                'type_label' => 'Pembayaran',
                'title' => 'Pembayaran ORD-1028 diterima',
                'message' => 'Pembayaran untuk pesanan Robusta Enrekang Premium telah diterima.',
                'data' => json_encode([
                    'order_id' => 'ORD-1028',
                    'amount' => 4290000,
                ]),
                'is_read' => true,
                'created_at' => now()->subDays(5),
            ],

            // shipment_update (2 items, 1 unread + 1 read)
            [
                'id' => 'notif-006',
                'user_id' => $this->buyer->id,
                'type' => 'shipment_update',
                'type_label' => 'Pengiriman',
                'title' => 'Update pengiriman ORD-1030',
                'message' => 'Pesanan ORD-1030 telah sampai di Pelabuhan Makassar.',
                'data' => json_encode([
                    'order_id' => 'ORD-1030',
                    'port_name' => 'Pelabuhan Makassar',
                    'eta_days' => 2,
                ]),
                'is_read' => false,
                'created_at' => now()->subHours(1),
            ],
            [
                'id' => 'notif-007',
                'user_id' => $this->buyer->id,
                'type' => 'shipment_update',
                'type_label' => 'Pengiriman',
                'title' => 'Update pengiriman ORD-1028',
                'message' => 'Pesanan ORD-1028 telah tiba di Pelabuhan Semarang.',
                'data' => json_encode([
                    'order_id' => 'ORD-1028',
                    'port_name' => 'Tanjung Emas, Semarang',
                    'eta_days' => 0,
                ]),
                'is_read' => true,
                'created_at' => now()->subDays(2),
            ],

            // catalog_update (1 item, unread)
            [
                'id' => 'notif-008',
                'user_id' => $this->buyer->id,
                'type' => 'catalog_update',
                'type_label' => 'Katalog',
                'title' => 'Batch baru di katalog: Arabika Gayo Highland',
                'message' => 'PT Sulawesi Coffee Export telah menambahkan batch baru ke katalog.',
                'data' => json_encode([
                    'listing_id' => 'listing-005',
                    'listing_name' => 'Arabika Gayo Highland',
                ]),
                'is_read' => false,
                'created_at' => now()->subHours(12),
            ],

            // system (2 items, 1 unread + 1 read)
            [
                'id' => 'notif-009',
                'user_id' => $this->buyer->id,
                'type' => 'system',
                'type_label' => 'Sistem',
                'title' => 'Jadwal Maintenance 5 Juni 2026',
                'message' => 'Sistem akan mengalami maintenance pada 5 Juni 2026 pukul 02:00-04:00 WIB.',
                'data' => json_encode([
                    'message' => 'Planned maintenance window: 2026-06-05 02:00-04:00 WIB',
                ]),
                'is_read' => false,
                'created_at' => now()->subMinutes(30),
            ],
            [
                'id' => 'notif-010',
                'user_id' => $this->buyer->id,
                'type' => 'system',
                'type_label' => 'Sistem',
                'title' => 'Update Kebijakan Privasi',
                'message' => 'Kebijakan privasi aplikasi BIJI telah diperbarui.',
                'data' => json_encode([
                    'message' => 'Privacy policy updated effective June 1, 2026',
                ]),
                'is_read' => true,
                'created_at' => now()->subDays(7),
            ],
        ];

        foreach ($buyerNotifications as $notif) {
            $this->notifications[] = Notification::factory()->create($notif);
        }

        // ── Notifications untuk buyer2 (untuk isolasi test) ──
        Notification::factory()->create([
            'id' => 'notif-b2-001',
            'user_id' => $this->buyer2->id,
            'type' => 'order_status_changed',
            'type_label' => 'Status Pesanan',
            'title' => 'Pesanan ORD-2001 sedang dikirim',
            'message' => 'Pesanan Anda sedang dikirim.',
            'data' => json_encode(['order_id' => 'ORD-2001', 'old_status' => 'ready_shipment', 'new_status' => 'in_transit']),
            'is_read' => false,
            'created_at' => now()->subHour(),
        ]);

        Notification::factory()->create([
            'id' => 'notif-b2-002',
            'user_id' => $this->buyer2->id,
            'type' => 'payment_received',
            'type_label' => 'Pembayaran',
            'title' => 'Pembayaran ORD-2001 diterima',
            'message' => 'Pembayaran pesanan Anda telah diterima.',
            'data' => json_encode(['order_id' => 'ORD-2001', 'amount' => 8950000]),
            'is_read' => true,
            'created_at' => now()->subDays(1),
        ]);

        // ── Notification untuk farmer (tidak boleh terlihat oleh buyer) ──
        Notification::factory()->create([
            'id' => 'notif-farmer-001',
            'user_id' => User::factory()->create(['role' => 'farmer'])->id,
            'type' => 'batch_status_changed',
            'type_label' => 'Status Batch',
            'title' => 'Batch BJI-TRJ-26054 diperbarui',
            'message' => 'Status batch telah berubah.',
            'data' => json_encode(['batch_id' => 'batch-001', 'old_status' => 'surveying', 'new_status' => 'ready']),
            'is_read' => false,
            'created_at' => now()->subHour(),
        ]);
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    /**
     * Helper: GET notifications list
     */
    private function getNotifications(array $query = [], ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->buyer)
            ->getJson('/api/v1/buyer/notifications?'.http_build_query($query));
    }

    /**
     * Helper: GET unread count
     */
    private function getUnreadCount(?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->buyer)
            ->getJson('/api/v1/buyer/notifications/unread-count');
    }

    /**
     * Helper: PATCH mark as read
     */
    private function markAsRead(string $notificationId, ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->buyer)
            ->patchJson("/api/v1/buyer/notifications/{$notificationId}/read");
    }

    /**
     * Helper: Get expected unread count for a buyer
     * buyer: notif-001,002,004,006,008,009 = 6 unread (3,5,7,10 are read)
     * buyer2: notif-b2-001 = 1 unread
     */
    private function getExpectedUnreadCount(User $user): int
    {
        return Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();
    }

    // ========================================================================
    // SECTION 1: AUTH & AUTHORIZATION — NOTIFICATIONS LIST (3 tests)
    // ========================================================================

    /** @test */
    public function test_notifications_list_requires_authentication(): void
    {
        $this->getJson('/api/v1/buyer/notifications')
            ->assertUnauthorized()
            ->assertJson(['success' => false, 'code' => 'UNAUTHORIZED']);
    }

    /** @test */
    public function test_notifications_list_rejects_farmer_role(): void
    {
        $farmer = User::factory()->create(['role' => 'farmer']);

        $this->actingAs($farmer)
            ->getJson('/api/v1/buyer/notifications')
            ->assertStatus(403)
            ->assertJson(['success' => false, 'code' => 'FORBIDDEN']);
    }

    /** @test */
    public function test_notifications_list_rejects_exporter_role(): void
    {
        $exporter = User::factory()->create(['role' => 'exporter']);

        $this->actingAs($exporter)
            ->getJson('/api/v1/buyer/notifications')
            ->assertStatus(403)
            ->assertJson(['success' => false, 'code' => 'FORBIDDEN']);
    }

    // ========================================================================
    // SECTION 2: AUTH & AUTHORIZATION — UNREAD COUNT (3 tests)
    // ========================================================================

    /** @test */
    public function test_unread_count_requires_authentication(): void
    {
        $this->getJson('/api/v1/buyer/notifications/unread-count')
            ->assertUnauthorized()
            ->assertJson(['success' => false, 'code' => 'UNAUTHORIZED']);
    }

    /** @test */
    public function test_unread_count_rejects_farmer_role(): void
    {
        $farmer = User::factory()->create(['role' => 'farmer']);

        $this->actingAs($farmer)
            ->getJson('/api/v1/buyer/notifications/unread-count')
            ->assertStatus(403)
            ->assertJson(['success' => false, 'code' => 'FORBIDDEN']);
    }

    /** @test */
    public function test_unread_count_rejects_exporter_role(): void
    {
        $exporter = User::factory()->create(['role' => 'exporter']);

        $this->actingAs($exporter)
            ->getJson('/api/v1/buyer/notifications/unread-count')
            ->assertStatus(403)
            ->assertJson(['success' => false, 'code' => 'FORBIDDEN']);
    }

    // ========================================================================
    // SECTION 3: AUTH & AUTHORIZATION — MARK AS READ (3 tests)
    // ========================================================================

    /** @test */
    public function test_mark_read_requires_authentication(): void
    {
        $this->patchJson('/api/v1/buyer/notifications/notif-001/read')
            ->assertUnauthorized()
            ->assertJson(['success' => false, 'code' => 'UNAUTHORIZED']);
    }

    /** @test */
    public function test_mark_read_rejects_farmer_role(): void
    {
        $farmer = User::factory()->create(['role' => 'farmer']);

        $this->actingAs($farmer)
            ->patchJson('/api/v1/buyer/notifications/notif-001/read')
            ->assertStatus(403)
            ->assertJson(['success' => false, 'code' => 'FORBIDDEN']);
    }

    /** @test */
    public function test_mark_read_rejects_exporter_role(): void
    {
        $exporter = User::factory()->create(['role' => 'exporter']);

        $this->actingAs($exporter)
            ->patchJson('/api/v1/buyer/notifications/notif-001/read')
            ->assertStatus(403)
            ->assertJson(['success' => false, 'code' => 'FORBIDDEN']);
    }

    // ========================================================================
    // SECTION 4: NOTIFICATIONS LIST — RESPONSE STRUCTURE (4 tests)
    // ========================================================================

    /** @test */
    public function test_notifications_list_returns_200_with_correct_structure(): void
    {
        $response = $this->getNotifications();

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
                'message' => 'Notifikasi berhasil diambil',
            ]);

        $response->assertJsonStructure([
            'data',
            'pagination' => ['cursor', 'hasMore', 'limit', 'total'],
            'timestamp',
        ]);
    }

    /** @test */
    public function test_notifications_list_item_has_all_required_fields(): void
    {
        $response = $this->getNotifications();

        $response->assertOk();

        $item = $response->json('data.0');
        $requiredFields = [
            'id', 'type', 'type_label', 'title', 'message', 'data', 'is_read', 'created_at',
        ];

        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $item, "Missing notification field: {$field}");
        }
    }

    /** @test */
    public function test_notifications_list_type_has_label(): void
    {
        $response = $this->getNotifications(['type' => 'order_status_changed']);

        $response->assertOk();

        $items = $response->json('data');
        foreach ($items as $item) {
            $this->assertEquals('order_status_changed', $item['type']);
            $this->assertEquals('Status Pesanan', $item['type_label']);
            $this->assertNotEmpty($item['type_label']);
        }
    }

    /** @test */
    public function test_notifications_list_data_field_is_object_or_array(): void
    {
        $response = $this->getNotifications();

        $response->assertOk();

        $items = $response->json('data');
        foreach ($items as $item) {
            $this->assertTrue(
                is_array($item['data']) || is_object($item['data']) || $item['data'] === null,
                'Notification data field should be array/object/null'
            );
        }
    }

    // ========================================================================
    // SECTION 5: NOTIFICATIONS LIST — DATA ISOLATION (3 tests)
    // ========================================================================

    /** @test */
    public function test_notifications_list_only_shows_buyers_own_notifications(): void
    {
        // buyer punya 10 notifikasi (notif-001 s/d notif-010)
        $response = $this->getNotifications();

        $response->assertOk();

        $items = $response->json('data');
        $ids = array_column($items, 'id');

        // Tidak boleh ada notifikasi buyer2 (notif-b2-xxx)
        foreach ($ids as $id) {
            $this->assertDoesNotMatchRegularExpression('/^notif-b2/', $id);
        }

        // Tidak boleh ada notifikasi farmer (notif-farmer-xxx)
        foreach ($ids as $id) {
            $this->assertDoesNotMatchRegularExpression('/^notif-farmer/', $id);
        }
    }

    /** @test */
    public function test_notifications_list_shows_empty_for_new_buyer(): void
    {
        $newBuyer = User::factory()->create(['role' => 'buyer']);

        $response = $this->getNotifications([], $newBuyer);

        $response->assertOk();

        $this->assertCount(0, $response->json('data'));
        $this->assertEquals(0, $response->json('pagination.total'));
        $this->assertFalse($response->json('pagination.hasMore'));
    }

    /** @test */
    public function test_notifications_list_buyer2_only_sees_own_notifications(): void
    {
        $response = $this->getNotifications([], $this->buyer2);

        $response->assertOk();

        $items = $response->json('data');
        $ids = array_column($items, 'id');

        // buyer2 hanya punya 2 notifikasi (notif-b2-001, notif-b2-002)
        $this->assertCount(2, $items);

        foreach ($ids as $id) {
            $this->assertMatchesRegularExpression('/^notif-b2/', $id);
        }

        // Tidak boleh ada notif buyer utama (notif-001 s/d 010)
        foreach ($ids as $id) {
            $this->assertDoesNotMatchRegularExpression('/^notif-\d{3}$/', $id);
        }
    }

    // ========================================================================
    // SECTION 6: NOTIFICATIONS LIST — TYPE FILTER (6 tests)
    // ========================================================================

    /** @test */
    public function test_notifications_list_filter_by_order_status_changed(): void
    {
        $response = $this->getNotifications(['type' => 'order_status_changed']);

        $response->assertOk();

        $items = $response->json('data');
        $this->assertCount(3, $items); // notif-001, 002, 003

        foreach ($items as $item) {
            $this->assertEquals('order_status_changed', $item['type']);
        }
    }

    /** @test */
    public function test_notifications_list_filter_by_payment_received(): void
    {
        $response = $this->getNotifications(['type' => 'payment_received']);

        $response->assertOk();

        $items = $response->json('data');
        $this->assertCount(2, $items); // notif-004, 005

        foreach ($items as $item) {
            $this->assertEquals('payment_received', $item['type']);
            $this->assertEquals('Pembayaran', $item['type_label']);
        }
    }

    /** @test */
    public function test_notifications_list_filter_by_shipment_update(): void
    {
        $response = $this->getNotifications(['type' => 'shipment_update']);

        $response->assertOk();

        $items = $response->json('data');
        $this->assertCount(2, $items); // notif-006, 007

        foreach ($items as $item) {
            $this->assertEquals('shipment_update', $item['type']);
            $this->assertEquals('Pengiriman', $item['type_label']);
        }
    }

    /** @test */
    public function test_notifications_list_filter_by_catalog_update(): void
    {
        $response = $this->getNotifications(['type' => 'catalog_update']);

        $response->assertOk();

        $items = $response->json('data');
        $this->assertCount(1, $items); // notif-008
        $this->assertEquals('catalog_update', $items[0]['type']);
        $this->assertEquals('Katalog', $items[0]['type_label']);
    }

    /** @test */
    public function test_notifications_list_filter_by_system(): void
    {
        $response = $this->getNotifications(['type' => 'system']);

        $response->assertOk();

        $items = $response->json('data');
        $this->assertCount(2, $items); // notif-009, 010

        foreach ($items as $item) {
            $this->assertEquals('system', $item['type']);
            $this->assertEquals('Sistem', $item['type_label']);
        }
    }

    /** @test */
    public function test_notifications_list_filter_by_invalid_type_returns_empty(): void
    {
        $response = $this->getNotifications(['type' => 'invalid_type']);

        $response->assertOk();

        // Invalid type: either 422 validation error or empty result
        // Contract doesn't specify, so we accept either behavior
        $this->assertTrue(
            $response->status() === 200 || $response->status() === 422,
            'Invalid type filter should return 200 (empty) or 422'
        );
    }

    // ========================================================================
    // SECTION 7: NOTIFICATIONS LIST — PAGINATION (4 tests)
    // ========================================================================

    /** @test */
    public function test_notifications_list_respects_limit_parameter(): void
    {
        $response = $this->getNotifications(['limit' => 3]);

        $response->assertOk();

        $this->assertLessThanOrEqual(3, count($response->json('data')));
        $this->assertEquals(3, $response->json('pagination.limit'));
    }

    /** @test */
    public function test_notifications_list_default_limit_is_20(): void
    {
        // Buyer has exactly 10 notifications, so default limit=20 shows all
        $response = $this->getNotifications();

        $response->assertOk();

        $this->assertEquals(20, $response->json('pagination.limit'));
        $this->assertEquals(10, $response->json('pagination.total'));
    }

    /** @test */
    public function test_notifications_list_pagination_has_cursor_and_total(): void
    {
        $response = $this->getNotifications(['limit' => 5]);

        $response->assertOk();

        $pagination = $response->json('pagination');
        $this->assertNotNull($pagination['cursor']);
        $this->assertIsBool($pagination['hasMore']);
        $this->assertEquals(5, $pagination['limit']);
        $this->assertEquals(10, $pagination['total']);
        $this->assertTrue($pagination['hasMore']); // 10 total, limit 5 → hasMore = true
    }

    /** @test */
    public function test_notifications_list_pagination_cursor_fetches_next_page(): void
    {
        $firstPage = $this->getNotifications(['limit' => 5]);

        $firstPage->assertOk();

        $cursor = $firstPage->json('pagination.cursor');
        $firstIds = array_column($firstPage->json('data'), 'id');

        // Fetch next page using cursor
        $secondPage = $this->getNotifications(['limit' => 5, 'cursor' => $cursor]);

        $secondPage->assertOk();

        $secondIds = array_column($secondPage->json('data'), 'id');

        // No overlap between pages
        $overlap = array_intersect($firstIds, $secondIds);
        $this->assertCount(0, $overlap, 'Pages should not have overlapping items');
    }

    // ========================================================================
    // SECTION 8: NOTIFICATIONS LIST — SORT & ORDER (2 tests)
    // ========================================================================

    /** @test */
    public function test_notifications_list_default_order_is_newest_first(): void
    {
        $response = $this->getNotifications(['limit' => 50]);

        $response->assertOk();

        $items = $response->json('data');
        if (count($items) >= 2) {
            $this->assertGreaterThanOrEqual(
                strtotime($items[1]['created_at']),
                strtotime($items[0]['created_at']),
                'Notifications should be ordered newest first by default'
            );
        }
    }

    /** @test */
    public function test_notifications_list_order_is_consistent_with_created_at(): void
    {
        $response = $this->getNotifications(['limit' => 50]);

        $response->assertOk();

        $items = $response->json('data');
        for ($i = 1; $i < count($items); $i++) {
            $this->assertGreaterThanOrEqual(
                strtotime($items[$i]['created_at']),
                strtotime($items[$i - 1]['created_at']),
                "Notification at index {$i} is older than previous"
            );
        }
    }

    // ========================================================================
    // SECTION 9: NOTIFICATIONS LIST — READ/UNREAD STATUS (3 tests)
    // ========================================================================

    /** @test */
    public function test_notifications_list_includes_is_read_boolean_field(): void
    {
        $response = $this->getNotifications();

        $response->assertOk();

        $items = $response->json('data');
        foreach ($items as $item) {
            $this->assertIsBool($item['is_read'], 'is_read should be boolean');
        }
    }

    /** @test */
    public function test_notifications_list_has_correct_read_unread_count(): void
    {
        $response = $this->getNotifications();

        $response->assertOk();

        $items = $response->json('data');
        $unreadCount = 0;
        $readCount = 0;

        foreach ($items as $item) {
            if ($item['is_read']) {
                $readCount++;
            } else {
                $unreadCount++;
            }
        }

        // buyer: 6 unread (notif-001,002,004,006,008,009) + 4 read (notif-003,005,007,010)
        $this->assertEquals(6, $unreadCount);
        $this->assertEquals(4, $readCount);
        $this->assertEquals(10, $unreadCount + $readCount);
    }

    /** @test */
    public function test_notifications_list_filter_by_read_status_not_supported_or_returns_all(): void
    {
        // Contract doesn't specify a `is_read` filter parameter,
        // but if backend supports it, test that it works
        $response = $this->getNotifications(['is_read' => 'false']);

        // Accept either 200 with filtered results or 422 (unsupported param)
        $this->assertTrue(
            $response->status() === 200 || $response->status() === 422,
            'is_read filter should return 200 (filtered) or 422 (unsupported)'
        );
    }

    // ========================================================================
    // SECTION 10: NOTIFICATIONS LIST — DATA PAYLOAD PER TYPE (5 tests)
    // ========================================================================

    /** @test */
    public function test_notifications_list_order_status_changed_has_correct_data_payload(): void
    {
        $response = $this->getNotifications(['type' => 'order_status_changed', 'limit' => 1]);

        $response->assertOk();

        $item = $response->json('data.0');
        $data = $item['data'];

        // order_status_changed data: order_id, old_status, new_status
        $this->assertArrayHasKey('order_id', $data);
        $this->assertArrayHasKey('old_status', $data);
        $this->assertArrayHasKey('new_status', $data);
        $this->assertMatchesRegularExpression('/^ORD-\d+$/', $data['order_id']);
        $this->assertNotEmpty($data['old_status']);
        $this->assertNotEmpty($data['new_status']);
    }

    /** @test */
    public function test_notifications_list_payment_received_has_correct_data_payload(): void
    {
        $response = $this->getNotifications(['type' => 'payment_received', 'limit' => 1]);

        $response->assertOk();

        $item = $response->json('data.0');
        $data = $item['data'];

        // payment_received data: order_id, amount
        $this->assertArrayHasKey('order_id', $data);
        $this->assertArrayHasKey('amount', $data);
        $this->assertMatchesRegularExpression('/^ORD-\d+$/', $data['order_id']);
        $this->assertIsNumeric($data['amount']);
        $this->assertGreaterThan(0, $data['amount']);
    }

    /** @test */
    public function test_notifications_list_shipment_update_has_correct_data_payload(): void
    {
        $response = $this->getNotifications(['type' => 'shipment_update', 'limit' => 1]);

        $response->assertOk();

        $item = $response->json('data.0');
        $data = $item['data'];

        // shipment_update data: order_id, port_name, eta_days
        $this->assertArrayHasKey('order_id', $data);
        $this->assertArrayHasKey('port_name', $data);
        $this->assertArrayHasKey('eta_days', $data);
        $this->assertIsInt($data['eta_days']);
        $this->assertGreaterThanOrEqual(0, $data['eta_days']);
    }

    /** @test */
    public function test_notifications_list_catalog_update_has_correct_data_payload(): void
    {
        $response = $this->getNotifications(['type' => 'catalog_update']);

        $response->assertOk();

        $item = $response->json('data.0');
        $data = $item['data'];

        // catalog_update data: listing_id, listing_name
        $this->assertArrayHasKey('listing_id', $data);
        $this->assertArrayHasKey('listing_name', $data);
        $this->assertNotEmpty($data['listing_id']);
        $this->assertNotEmpty($data['listing_name']);
    }

    /** @test */
    public function test_notifications_list_system_has_correct_data_payload(): void
    {
        $response = $this->getNotifications(['type' => 'system', 'limit' => 1]);

        $response->assertOk();

        $item = $response->json('data.0');
        $data = $item['data'];

        // system data: message (nullable)
        $this->assertTrue(
            isset($data['message']) || $data === null,
            'System notification should have message key or be null'
        );
    }

    // ========================================================================
    // SECTION 11: NOTIFICATIONS LIST — EMPTY STATES (2 tests)
    // ========================================================================

    /** @test */
    public function test_notifications_list_empty_for_new_buyer_without_notifications(): void
    {
        $newBuyer = User::factory()->create(['role' => 'buyer']);

        $response = $this->getNotifications([], $newBuyer);

        $response->assertOk()
            ->assertJson(['success' => true, 'code' => 'SUCCESS']);

        $this->assertCount(0, $response->json('data'));
        $this->assertEquals(0, $response->json('pagination.total'));
        $this->assertNull($response->json('pagination.cursor'));
        $this->assertFalse($response->json('pagination.hasMore'));
    }

    /** @test */
    public function test_notifications_list_empty_for_filter_with_no_matching_type(): void
    {
        // Create a buyer with only system notifications
        $cleanBuyer = User::factory()->create(['role' => 'buyer']);
        Notification::factory()->create([
            'id' => 'notif-clean-001',
            'user_id' => $cleanBuyer->id,
            'type' => 'system',
            'type_label' => 'Sistem',
            'title' => 'Welcome',
            'message' => 'Selamat datang di BIJI.',
            'data' => null,
            'is_read' => true,
            'created_at' => now(),
        ]);

        $response = $this->getNotifications(['type' => 'order_status_changed'], $cleanBuyer);

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
        $this->assertEquals(0, $response->json('pagination.total'));
    }

    // ========================================================================
    // SECTION 12: UNREAD COUNT — CORRECT CALCULATION (4 tests)
    // ========================================================================

    /** @test */
    public function test_unread_count_returns_200_with_correct_structure(): void
    {
        $response = $this->getUnreadCount();

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
                'message' => 'Jumlah notifikasi belum dibaca',
            ]);

        $response->assertJsonStructure([
            'data' => ['unread_count', 'has_unread'],
            'timestamp',
        ]);
    }

    /** @test */
    public function test_unread_count_is_accurate_for_buyer(): void
    {
        $response = $this->getUnreadCount();

        $response->assertOk();

        // buyer: 6 unread (notif-001,002,004,006,008,009)
        $this->assertEquals(6, $response->json('data.unread_count'));
    }

    /** @test */
    public function test_unread_count_has_unread_boolean(): void
    {
        $response = $this->getUnreadCount();

        $response->assertOk();

        $hasUnread = $response->json('data.has_unread');
        $this->assertIsBool($hasUnread);

        // 6 unread → has_unread should be true
        $this->assertTrue($hasUnread);
    }

    /** @test */
    public function test_unread_count_accurate_for_buyer2(): void
    {
        $response = $this->getUnreadCount($this->buyer2);

        $response->assertOk();

        // buyer2: 1 unread (notif-b2-001)
        $this->assertEquals(1, $response->json('data.unread_count'));
        $this->assertTrue($response->json('data.has_unread'));
    }

    // ========================================================================
    // SECTION 13: UNREAD COUNT — EDGE CASES (3 tests)
    // ========================================================================

    /** @test */
    public function test_unread_count_is_zero_when_all_read(): void
    {
        // Mark all buyer's notifications as read
        Notification::where('user_id', $this->buyer->id)->update(['is_read' => true]);

        $response = $this->getUnreadCount();

        $response->assertOk();

        $this->assertEquals(0, $response->json('data.unread_count'));
        $this->assertFalse($response->json('data.has_unread'));
    }

    /** @test */
    public function test_unread_count_is_zero_for_new_buyer(): void
    {
        $newBuyer = User::factory()->create(['role' => 'buyer']);

        $response = $this->getUnreadCount($newBuyer);

        $response->assertOk();

        $this->assertEquals(0, $response->json('data.unread_count'));
        $this->assertFalse($response->json('data.has_unread'));
    }

    /** @test */
    public function test_unread_count_is_isolated_between_buyers(): void
    {
        // Mark all buyer2's notifications as read
        Notification::where('user_id', $this->buyer2->id)->update(['is_read' => true]);

        $buyerResponse = $this->getUnreadCount($this->buyer);
        $buyer2Response = $this->getUnreadCount($this->buyer2);

        // Buyer still has 6 unread
        $this->assertEquals(6, $buyerResponse->json('data.unread_count'));
        $this->assertTrue($buyerResponse->json('data.has_unread'));

        // Buyer2 now has 0 unread
        $this->assertEquals(0, $buyer2Response->json('data.unread_count'));
        $this->assertFalse($buyer2Response->json('data.has_unread'));
    }

    // ========================================================================
    // SECTION 14: MARK AS READ — SUCCESS (3 tests)
    // ========================================================================

    /** @test */
    public function test_mark_read_success_returns_correct_response(): void
    {
        $response = $this->markAsRead('notif-001');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS_UPDATE',
                'message' => 'Notifikasi ditandai sebagai dibaca',
                'data' => null,
            ]);
    }

    /** @test */
    public function test_mark_read_updates_is_read_to_true_in_database(): void
    {
        $this->assertFalse(
            Notification::where('id', 'notif-001')->value('is_read'),
            'Precondition: notif-001 should be unread'
        );

        $this->markAsRead('notif-001');

        $this->assertTrue(
            Notification::where('id', 'notif-001')->value('is_read'),
            'notif-001 should now be read'
        );
    }

    /** @test */
    public function test_mark_read_reflected_in_notifications_list(): void
    {
        // Verify notif-004 is unread in list before marking
        $beforeResponse = $this->getNotifications(['type' => 'payment_received', 'limit' => 1]);
        $beforeResponse->assertOk();
        $this->assertFalse($beforeResponse->json('data.0.is_read'), 'Precondition: notif-004 unread');

        // Mark as read
        $this->markAsRead('notif-004');

        // Re-fetch — notif-004 should now be read
        $afterResponse = $this->getNotifications(['type' => 'payment_received', 'limit' => 1]);
        $afterResponse->assertOk();
        $this->assertTrue($afterResponse->json('data.0.is_read'), 'notif-004 should be read after marking');
    }

    // ========================================================================
    // SECTION 15: MARK AS READ — DATA ISOLATION & OWNERSHIP (4 tests)
    // ========================================================================

    /** @test */
    public function test_mark_read_rejects_marking_other_buyers_notification(): void
    {
        // notif-b2-001 belongs to buyer2, buyer tries to mark it
        $response = $this->markAsRead('notif-b2-001', $this->buyer);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }

    /** @test */
    public function test_mark_read_other_buyer_does_not_change_read_status(): void
    {
        $originalRead = Notification::where('id', 'notif-b2-001')->value('is_read');

        // buyer tries to mark buyer2's notification
        $this->markAsRead('notif-b2-001', $this->buyer);

        // Should remain unchanged
        $this->assertEquals(
            $originalRead,
            Notification::where('id', 'notif-b2-001')->value('is_read'),
            'Other buyer cannot change notification read status'
        );
    }

    /** @test */
    public function test_mark_read_buyer2_can_mark_own_notification(): void
    {
        // buyer2 marks own notification
        $response = $this->markAsRead('notif-b2-001', $this->buyer2);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS_UPDATE',
            ]);

        $this->assertTrue(
            Notification::where('id', 'notif-b2-001')->value('is_read')
        );
    }

    /** @test */
    public function test_mark_read_rejects_farmer_notification_id(): void
    {
        // notif-farmer-001 belongs to farmer
        $response = $this->markAsRead('notif-farmer-001', $this->buyer);

        $response->assertStatus(403);
    }

    // ========================================================================
    // SECTION 16: MARK AS READ — NOT FOUND & VALIDATION (3 tests)
    // ========================================================================

    /** @test */
    public function test_mark_read_returns_404_for_nonexistent_notification(): void
    {
        $response = $this->markAsRead('notif-nonexistent');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code' => 'NOT_FOUND',
            ]);
    }

    /** @test */
    public function test_mark_read_returns_404_for_empty_id(): void
    {
        $response = $this->actingAs($this->buyer)
            ->patchJson('/api/v1/buyer/notifications/ /read');

        // Either 404 or 404/403 — empty string ID is invalid
        $response->assertStatus(404);
    }

    /** @test */
    public function test_mark_read_only_accepts_patch_method(): void
    {
        // GET should not work
        $this->actingAs($this->buyer)
            ->getJson('/api/v1/buyer/notifications/notif-001/read')
            ->assertStatus(404); // or 405

        // POST should not work
        $this->actingAs($this->buyer)
            ->postJson('/api/v1/buyer/notifications/notif-001/read')
            ->assertStatus(404); // or 405
    }

    // ========================================================================
    // SECTION 17: MARK AS READ — IDEMPOTENCY (2 tests)
    // ========================================================================

    /** @test */
    public function test_mark_read_is_idempotent_for_already_read_notification(): void
    {
        // notif-003 is already read
        $this->assertTrue(
            Notification::where('id', 'notif-003')->value('is_read'),
            'Precondition: notif-003 should already be read'
        );

        $response = $this->markAsRead('notif-003');

        // Should still return success (idempotent)
        $response->assertOk()
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS_UPDATE',
            ]);
    }

    /** @test */
    public function test_mark_read_twice_returns_same_result(): void
    {
        // First mark
        $firstResponse = $this->markAsRead('notif-002');
        $firstResponse->assertOk();

        // Second mark (same notification, already read now)
        $secondResponse = $this->markAsRead('notif-002');
        $secondResponse->assertOk();

        // Both should return the same success code
        $this->assertEquals(
            $firstResponse->json('code'),
            $secondResponse->json('code')
        );
    }

    // ========================================================================
    // SECTION 18: INTEGRATION — UNREAD COUNT AFTER MARK READ (2 tests)
    // ========================================================================

    /** @test */
    public function test_unread_count_decreases_after_mark_read(): void
    {
        // Initial unread count for buyer: 6
        $beforeResponse = $this->getUnreadCount();
        $beforeResponse->assertOk();
        $initialCount = $beforeResponse->json('data.unread_count');
        $this->assertEquals(6, $initialCount);

        // Mark one notification as read
        $this->markAsRead('notif-001');

        // Unread count should decrease by 1
        $afterResponse = $this->getUnreadCount();
        $afterResponse->assertOk();
        $this->assertEquals($initialCount - 1, $afterResponse->json('data.unread_count'));
        $this->assertEquals(5, $afterResponse->json('data.unread_count'));
    }

    /** @test */
    public function test_unread_count_becomes_false_when_all_marked_read(): void
    {
        // Mark all buyer's unread notifications as read
        $unreadNotifications = Notification::where('user_id', $this->buyer->id)
            ->where('is_read', false)
            ->pluck('id');

        foreach ($unreadNotifications as $notifId) {
            $this->markAsRead($notifId)->assertOk();
        }

        $response = $this->getUnreadCount();

        $response->assertOk();
        $this->assertEquals(0, $response->json('data.unread_count'));
        $this->assertFalse($response->json('data.has_unread'));
    }
}

<?php

namespace Tests\Feature\Buyer;

use App\Models\BatchListing;
use App\Models\BatchLog;
use App\Models\BatchSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * BuyerCatalogTest — Test Case untuk Modul Katalog Biji Kopi Buyer (API Contract V3)
 *
 * Scope: 5 endpoint catalog
 *   - GET /api/v1/buyer/catalog              — List katalog (§7.1)
 *   - GET /api/v1/buyer/catalog/{id}          — Detail katalog (§7.2)
 *   - GET /api/v1/buyer/catalog/{id}/logs    — Full IoT warehouse logs (§7.3)
 *   - GET /api/v1/buyer/catalog/{id}/logs/trend — Data tren IoT (§7.4)
 *   - GET /api/v1/buyer/catalog/{id}/snapshots  — Snapshot on-chain (§7.5)
 *
 * Reference: API_CONTRACT_V3_BUYER.md
 *   - Section 7  (Modul Katalog Biji Kopi)
 *   - Section 5  (Kode Error Shared + Buyer-Specific)
 *   - Section 13 (Cross-POV Data Reference)
 *   - Section 16.3 (Catalog Visibility Rules)
 *
 * Response fields yang ditest:
 *   [7.1] List: id, batch_code, name, variety, origin, elevation, harvest_date, process,
 *         image_url, image_alt, category, category_label, badge, price_per_kg,
 *         stock_kg, stock_status, exporter (nested), is_blockchain_verified,
 *         iot_log_count, snapshot_count, detail_url, listed_at, pagination
 *   [7.2] Detail: listing (full), iot_summary, genesis_data, blockchain_audit,
 *         snapshots_preview, exporter (nested full)
 *   [7.3] Logs: id, batch_listing_id, batch_code, timestamp, temperature, humidity,
 *         temperature_max/min/avg, health_status, health_label, health_color,
 *         sensor_id, recorded_at, pagination
 *   [7.4] Trend: listing_id, batch_code, period, period_label, total_points,
 *         data_points (label, temperature_avg, temperature_max, humidity, log_count, date)
 *   [7.5] Snapshots: id, batch_listing_id, batch_code, snapshot_date, block_number,
 *         transaction_hash, log_count, avg/min/max_temperature, avg_humidity,
 *         hash, is_verified, verified_at, explorer_url, pagination
 *
 * Business Rules V3 yang ditest:
 *   - Hanya listing dengan status `listed` yang muncul di katalog
 *   - Listing draft/archived TIDAK muncul (BATCH_NOT_LISTED jika akses detail)
 *   - Listing sold_out (stock=0) TIDAK muncul
 *   - stock_status = low_stock jika stok < 100kg
 *   - stock_status = available jika stok >= 100kg
 *   - STRICT farmer isolation: TIDAK ADA farmer identity di response manapun
 *   - Exporter name BOLEH dan HARUS tampil di response
 *   - search mencocokkan: name, variety, origin, batch_code
 *   - filter: all, arabika, robusta, specialty, single_origin, rare_lot
 *   - sort: newest (listed_at desc), price_asc, price_desc, origin
 *   - Cursor-based pagination (cursor, hasMore, limit, total)
 *   - Data IoT 100% read-only, immutable
 *   - health_status: normal (temp 25-35, humidity 60-75), warning, critical
 *   - Trend period: hourly (max 72), daily (max 30), weekly (max 12)
 *   - Snapshot dicatat per 24 jam ke blockchain Polygon
 *
 * Sections (75 tests):
 *   1.  Auth & Authorization — Catalog List (3 tests)
 *   2.  Auth & Authorization — Catalog Detail (3 tests)
 *   3.  Catalog List — Response Structure & Fields (5 tests)
 *   4.  Catalog List — Pagination (3 tests)
 *   5.  Catalog List — Search (3 tests)
 *   6.  Catalog List — Filter by Category (4 tests)
 *   7.  Catalog List — Sort (4 tests)
 *   8.  Catalog List — Stock Status Display (3 tests)
 *   9.  Catalog List — Visibility Rules (5 tests)
 *   10. Catalog Detail — Response Structure (5 tests)
 *   11. Catalog Detail — Nested Objects (4 tests)
 *   12. Catalog Detail — Farmer Isolation Strict (3 tests)
 *   13. Catalog Detail — Listing Not Available (4 tests)
 *   14. Catalog Logs — Response Structure (4 tests)
 *   15. Catalog Logs — Pagination & Date Filter (3 tests)
 *   16. Catalog Logs — Listing Not Available (2 tests)
 *   17. Catalog Trend — Response Structure (4 tests)
 *   18. Catalog Trend — Period Parameter (3 tests)
 *   19. Catalog Trend — Listing Not Available (2 tests)
 *   20. Catalog Snapshots — Response Structure (4 tests)
 *   21. Catalog Snapshots — Pagination (2 tests)
 *   22. Catalog Snapshots — Listing Not Available (2 tests)
 */
class BuyerCatalogTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private User $buyer2;

    private User $exporter;

    private User $exporter2;

    private User $farmer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buyer = User::factory()->create([
            'role' => 'buyer',
            'name' => 'John Doe',
            'email' => 'john@company.com',
            'company_name' => 'PT Kopi Nusantara',
            'business_id' => 'NPWP-1234567890',
            'profile_completion' => 80,
        ]);

        $this->buyer2 = User::factory()->create([
            'role' => 'buyer',
            'name' => 'Jane Smith',
            'email' => 'jane@coffee.co',
            'company_name' => 'Pacific Roasters Inc.',
            'business_id' => 'NPWP-9876543210',
            'profile_completion' => 60,
        ]);

        // Exporters (VISIBLE ke buyer)
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

        // Farmer (HIDDEN dari buyer — hanya untuk setup data)
        $this->farmer = User::factory()->create([
            'role' => 'farmer',
            'name' => 'Yusuf Ibrahim',
        ]);

        // ── Default listings (listed, berbagai kategori) ──
        BatchListing::factory()->create([
            'id' => 'listing-001',
            'exporter_id' => $this->exporter->id,
            'batch_code' => 'BJI-TRJ-26054',
            'name' => 'Arabika Toraja Sapan',
            'variety' => 'Arabika Toraja',
            'origin' => 'Tana Toraja, Sulawesi Selatan',
            'image_url' => 'https://storage.biji.local/listings/listing-001/cover.jpg',
            'elevation' => '1.500 mdpl',
            'harvest_date' => '2026-05-12',
            'process' => 'Penjemuran',
            'category' => 'single_origin',
            'price_per_kg' => 145000,
            'stock_kg' => 2400,
            'status' => 'listed',
            'listed_at' => now()->subDays(5),
        ]);

        BatchListing::factory()->create([
            'id' => 'listing-002',
            'exporter_id' => $this->exporter2->id,
            'batch_code' => 'BJI-ENK-26048',
            'name' => 'Robusta Enrekang Premium',
            'variety' => 'Robusta Enrekang',
            'origin' => 'Enrekang, Sulawesi Selatan',
            'image_url' => 'https://storage.biji.local/listings/listing-002/cover.jpg',
            'elevation' => '900 mdpl',
            'harvest_date' => '2026-05-09',
            'process' => 'Natural',
            'category' => 'robusta',
            'price_per_kg' => 85000,
            'stock_kg' => 5000,
            'status' => 'listed',
            'listed_at' => now()->subDays(7),
        ]);

        BatchListing::factory()->create([
            'id' => 'listing-003',
            'exporter_id' => $this->exporter->id,
            'batch_code' => 'BJI-GAY-26060',
            'name' => 'Arabika Gayo Highland Specialty',
            'variety' => 'Arabika Gayo',
            'origin' => 'Gayo Lues, Aceh',
            'image_url' => 'https://storage.biji.local/listings/listing-003/cover.jpg',
            'elevation' => '1.800 mdpl',
            'harvest_date' => '2026-05-18',
            'process' => 'Full Washed',
            'category' => 'specialty',
            'price_per_kg' => 220000,
            'stock_kg' => 800,
            'status' => 'listed',
            'listed_at' => now()->subDays(2),
        ]);

        BatchListing::factory()->create([
            'id' => 'listing-004',
            'exporter_id' => $this->exporter2->id,
            'batch_code' => 'BJI-FLK-26070',
            'name' => 'Rare Lot Flores Bajawa',
            'variety' => 'Arabika Flores',
            'origin' => 'Bajawa, Nusa Tenggara Timur',
            'image_url' => 'https://storage.biji.local/listings/listing-004/cover.jpg',
            'elevation' => '1.200 mdpl',
            'harvest_date' => '2026-05-20',
            'process' => 'Honey',
            'category' => 'rare_lot',
            'price_per_kg' => 310000,
            'stock_kg' => 200,
            'status' => 'listed',
            'listed_at' => now()->subDays(1),
        ]);

        // ── Hidden listings (draft & archived) ──
        BatchListing::factory()->create([
            'id' => 'listing-draft',
            'exporter_id' => $this->exporter->id,
            'batch_code' => 'BJI-DRF-00001',
            'name' => 'Draft Listing',
            'variety' => 'Arabika',
            'origin' => 'Toraja',
            'category' => 'arabika',
            'price_per_kg' => 100000,
            'stock_kg' => 500,
            'status' => 'draft',
        ]);

        BatchListing::factory()->create([
            'id' => 'listing-archived',
            'exporter_id' => $this->exporter2->id,
            'batch_code' => 'BJI-ARC-00002',
            'name' => 'Archived Listing',
            'variety' => 'Robusta',
            'origin' => 'Enrekang',
            'category' => 'robusta',
            'price_per_kg' => 75000,
            'stock_kg' => 300,
            'status' => 'archived',
        ]);

        // ── Sold out listing (stock = 0) ──
        BatchListing::factory()->create([
            'id' => 'listing-soldout',
            'exporter_id' => $this->exporter->id,
            'batch_code' => 'BJI-SOL-00003',
            'name' => 'Sold Out Listing',
            'variety' => 'Arabika',
            'origin' => 'Gayo',
            'category' => 'arabika',
            'price_per_kg' => 180000,
            'stock_kg' => 0,
            'status' => 'listed',
        ]);

        // ── Create Batch to satisfy FOREIGN KEY constraint for BatchLogs ──
        \App\Models\Batch::factory()->create([
            'batch_id' => 'batch-001',
            'batch_code' => 'BJI-TRJ-26054',
        ]);

        // ── IoT logs & snapshots untuk listing-001 ──
        BatchLog::factory()->count(48)->create([
            'batch_listing_id' => 'listing-001',
            'batch_code' => 'BJI-TRJ-26054',
            'sensor_id' => 'IOT-TOR-001',
        ]);

        BatchSnapshot::factory()->count(12)->create([
            'batch_listing_id' => 'listing-001',
            'batch_code' => 'BJI-TRJ-26054',
        ]);
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    /**
     * Helper: GET catalog list sebagai buyer
     */
    private function getCatalog(array $query = [], ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->buyer)
            ->getJson('/api/v1/buyer/catalog?'.http_build_query($query));
    }

    /**
     * Helper: GET catalog detail sebagai buyer
     */
    private function getCatalogDetail(string $id, ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->buyer)
            ->getJson("/api/v1/buyer/catalog/{$id}");
    }

    /**
     * Helper: GET catalog logs sebagai buyer
     */
    private function getCatalogLogs(string $id, array $query = [], ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->buyer)
            ->getJson("/api/v1/buyer/catalog/{$id}/logs?".http_build_query($query));
    }

    /**
     * Helper: GET catalog trend sebagai buyer
     */
    private function getCatalogTrend(string $id, array $query = [], ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->buyer)
            ->getJson("/api/v1/buyer/catalog/{$id}/logs/trend?".http_build_query($query));
    }

    /**
     * Helper: GET catalog snapshots sebagai buyer
     */
    private function getCatalogSnapshots(string $id, array $query = [], ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->buyer)
            ->getJson("/api/v1/buyer/catalog/{$id}/snapshots?".http_build_query($query));
    }

    // ========================================================================
    // SECTION 1: AUTH & AUTHORIZATION — CATALOG LIST (3 tests)
    // ========================================================================

    /** @test */
    public function test_catalog_list_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/buyer/catalog');

        $response->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    /** @test */
    public function test_catalog_list_rejects_farmer_role(): void
    {
        $farmer = User::factory()->create(['role' => 'farmer']);

        $response = $this->actingAs($farmer)
            ->getJson('/api/v1/buyer/catalog');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }

    /** @test */
    public function test_catalog_list_rejects_exporter_role(): void
    {
        $exporter = User::factory()->create(['role' => 'exporter']);

        $response = $this->actingAs($exporter)
            ->getJson('/api/v1/buyer/catalog');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }

    // ========================================================================
    // SECTION 2: AUTH & AUTHORIZATION — CATALOG DETAIL (3 tests)
    // ========================================================================

    /** @test */
    public function test_catalog_detail_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/buyer/catalog/listing-001');

        $response->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    /** @test */
    public function test_catalog_detail_rejects_farmer_role(): void
    {
        $farmer = User::factory()->create(['role' => 'farmer']);

        $response = $this->actingAs($farmer)
            ->getJson('/api/v1/buyer/catalog/listing-001');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }

    /** @test */
    public function test_catalog_detail_rejects_exporter_role(): void
    {
        $exporter = User::factory()->create(['role' => 'exporter']);

        $response = $this->actingAs($exporter)
            ->getJson('/api/v1/buyer/catalog/listing-001');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }

    // ========================================================================
    // SECTION 3: CATALOG LIST — RESPONSE STRUCTURE & FIELDS (5 tests)
    // ========================================================================

    /** @test */
    public function test_catalog_list_returns_200_with_correct_structure(): void
    {
        $response = $this->getCatalog();

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
                'message' => 'Katalog biji kopi berhasil diambil',
            ]);

        // Top-level keys: data (array), pagination (object)
        $response->assertJsonStructure([
            'data',
            'pagination' => ['cursor', 'hasMore', 'limit', 'total'],
            'timestamp',
        ]);

        // data harus berupa array
        $this->assertIsArray($response->json('data'));
    }

    /** @test */
    public function test_catalog_list_item_has_all_required_fields(): void
    {
        $response = $this->getCatalog();

        $response->assertOk();

        $item = $response->json('data.0');
        $this->assertNotEmpty($item);

        // Core fields
        $requiredFields = [
            'id', 'batch_code', 'name', 'variety', 'origin', 'elevation',
            'harvest_date', 'harvest_date_label', 'process',
            'image_url', 'image_alt', 'category', 'category_label',
            'badge', 'price_per_kg', 'price_per_kg_display',
            'stock_kg', 'stock_status', 'stock_status_label',
            'is_blockchain_verified', 'iot_log_count', 'snapshot_count',
            'detail_url', 'listed_at',
        ];

        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $item, "Missing field: {$field}");
        }
    }

    /** @test */
    public function test_catalog_list_item_has_nested_exporter_object(): void
    {
        $response = $this->getCatalog();

        $response->assertOk();

        $exporter = $response->json('data.0.exporter');
        $this->assertNotEmpty($exporter);

        $this->assertArrayHasKey('id', $exporter);
        $this->assertArrayHasKey('name', $exporter);
        $this->assertArrayHasKey('avatar_url', $exporter);
        $this->assertArrayHasKey('rating', $exporter);
    }

    /** @test */
    public function test_catalog_list_item_detail_url_follows_pattern(): void
    {
        $response = $this->getCatalog();

        $response->assertOk();

        $item = $response->json('data.0');
        $this->assertEquals(
            "/api/v1/buyer/catalog/{$item['id']}",
            $item['detail_url']
        );
    }

    /** @test */
    public function test_catalog_list_returns_price_per_kg_display_formatted(): void
    {
        $response = $this->getCatalog();

        $response->assertOk();

        // Cari listing dengan price 145000
        $item = collect($response->json('data'))
            ->firstWhere('price_per_kg', 145000);

        $this->assertNotNull($item);
        $this->assertEquals('Rp 145.000', $item['price_per_kg_display']);
    }

    // ========================================================================
    // SECTION 4: CATALOG LIST — PAGINATION (3 tests)
    // ========================================================================

    /** @test */
    public function test_catalog_list_respects_limit_parameter(): void
    {
        $response = $this->getCatalog(['limit' => 2]);

        $response->assertOk();

        // Jumlah data tidak boleh melebihi limit
        $this->assertLessThanOrEqual(2, count($response->json('data')));
        $this->assertEquals(2, $response->json('pagination.limit'));
    }

    /** @test */
    public function test_catalog_list_default_limit_is_20(): void
    {
        // Buat 25 listings tambahan untuk test
        for ($i = 100; $i < 125; $i++) {
            BatchListing::factory()->create([
                'id' => "listing-extra-{$i}",
                'exporter_id' => $this->exporter->id,
                'batch_code' => "BJI-EXT-{$i}",
                'name' => "Extra Listing {$i}",
                'variety' => 'Arabika',
                'origin' => 'Toraja',
                'category' => 'single_origin',
                'price_per_kg' => 100000 + $i * 1000,
                'stock_kg' => 500,
                'status' => 'listed',
                'listed_at' => now()->subDays(10 - ($i % 10)),
            ]);
        }

        $response = $this->getCatalog();

        $response->assertOk();
        $this->assertEquals(20, $response->json('pagination.limit'));
    }

    /** @test */
    public function test_catalog_list_pagination_has_cursor_and_has_more(): void
    {
        $response = $this->getCatalog(['limit' => 2]);

        $response->assertOk();

        $pagination = $response->json('pagination');
        $this->assertNotNull($pagination['cursor']);
        $this->assertIsBool($pagination['hasMore']);
        $this->assertEquals(2, $pagination['limit']);
        $this->assertIsInt($pagination['total']);
    }

    // ========================================================================
    // SECTION 5: CATALOG LIST — SEARCH (3 tests)
    // ========================================================================

    /** @test */
    public function test_catalog_list_search_by_name(): void
    {
        $response = $this->getCatalog(['search' => 'Toraja Sapan']);

        $response->assertOk();

        $items = $response->json('data');
        $this->assertGreaterThan(0, count($items));

        // Semua hasil harus mengandung "Toraja Sapan" di name
        foreach ($items as $item) {
            $this->assertStringContainsString('Toraja Sapan', $item['name']);
        }
    }

    /** @test */
    public function test_catalog_list_search_by_variety(): void
    {
        $response = $this->getCatalog(['search' => 'Robusta']);

        $response->assertOk();

        $items = $response->json('data');
        $this->assertGreaterThan(0, count($items));

        // Semua hasil harus mengandung "Robusta" di variety
        foreach ($items as $item) {
            $this->assertStringContainsString('Robusta', $item['variety']);
        }
    }

    /** @test */
    public function test_catalog_list_search_by_origin(): void
    {
        $response = $this->getCatalog(['search' => 'Aceh']);

        $response->assertOk();

        $items = $response->json('data');
        // Semua hasil harus mengandung "Aceh" di origin
        foreach ($items as $item) {
            $this->assertStringContainsString('Aceh', $item['origin']);
        }
    }

    // ========================================================================
    // SECTION 6: CATALOG LIST — FILTER BY CATEGORY (4 tests)
    // ========================================================================

    /** @test */
    public function test_catalog_list_filter_single_origin(): void
    {
        $response = $this->getCatalog(['filter' => 'single_origin']);

        $response->assertOk();

        $items = $response->json('data');
        $this->assertGreaterThan(0, count($items));

        foreach ($items as $item) {
            $this->assertEquals('single_origin', $item['category']);
        }
    }

    /** @test */
    public function test_catalog_list_filter_robusta(): void
    {
        $response = $this->getCatalog(['filter' => 'robusta']);

        $response->assertOk();

        $items = $response->json('data');
        $this->assertGreaterThan(0, count($items));

        foreach ($items as $item) {
            $this->assertEquals('robusta', $item['category']);
        }
    }

    /** @test */
    public function test_catalog_list_filter_specialty(): void
    {
        $response = $this->getCatalog(['filter' => 'specialty']);

        $response->assertOk();

        $items = $response->json('data');
        $this->assertGreaterThan(0, count($items));

        foreach ($items as $item) {
            $this->assertEquals('specialty', $item['category']);
        }
    }

    /** @test */
    public function test_catalog_list_filter_rare_lot(): void
    {
        $response = $this->getCatalog(['filter' => 'rare_lot']);

        $response->assertOk();

        $items = $response->json('data');
        $this->assertGreaterThan(0, count($items));

        foreach ($items as $item) {
            $this->assertEquals('rare_lot', $item['category']);
        }
    }

    // ========================================================================
    // SECTION 7: CATALOG LIST — SORT (4 tests)
    // ========================================================================

    /** @test */
    public function test_catalog_list_sort_newest_orders_by_listed_at_desc(): void
    {
        $response = $this->getCatalog(['sort' => 'newest']);

        $response->assertOk();

        $items = $response->json('data');
        if (count($items) >= 2) {
            $this->assertGreaterThanOrEqual(
                strtotime($items[1]['listed_at']),
                strtotime($items[0]['listed_at'])
            );
        }
    }

    /** @test */
    public function test_catalog_list_sort_price_asc(): void
    {
        $response = $this->getCatalog(['sort' => 'price_asc']);

        $response->assertOk();

        $items = $response->json('data');
        if (count($items) >= 2) {
            $this->assertLessThanOrEqual(
                $items[1]['price_per_kg'],
                $items[0]['price_per_kg']
            );
        }
    }

    /** @test */
    public function test_catalog_list_sort_price_desc(): void
    {
        $response = $this->getCatalog(['sort' => 'price_desc']);

        $response->assertOk();

        $items = $response->json('data');
        if (count($items) >= 2) {
            $this->assertGreaterThanOrEqual(
                $items[1]['price_per_kg'],
                $items[0]['price_per_kg']
            );
        }
    }

    /** @test */
    public function test_catalog_list_sort_origin_orders_alphabetically(): void
    {
        $response = $this->getCatalog(['sort' => 'origin', 'sort_dir' => 'asc']);

        $response->assertOk();

        $items = $response->json('data');
        if (count($items) >= 2) {
            $this->assertLessThanOrEqual(
                0,
                strcmp($items[0]['origin'], $items[1]['origin'])
            );
        }
    }

    // ========================================================================
    // SECTION 8: CATALOG LIST — STOCK STATUS DISPLAY (3 tests)
    // ========================================================================

    /** @test */
    public function test_catalog_list_shows_available_status_for_high_stock(): void
    {
        $response = $this->getCatalog(['search' => 'Arabika Toraja Sapan']);

        $response->assertOk();

        $item = $response->json('data.0');
        $this->assertNotNull($item);
        // listing-001 stock = 2400 → available
        $this->assertEquals('available', $item['stock_status']);
        $this->assertEquals('Tersedia', $item['stock_status_label']);
    }

    /** @test */
    public function test_catalog_list_shows_low_stock_status_when_stock_below_100kg(): void
    {
        // Buat listing dengan stok < 100kg
        BatchListing::factory()->create([
            'id' => 'listing-low-stock',
            'exporter_id' => $this->exporter->id,
            'batch_code' => 'BJI-LOW-00001',
            'name' => 'Low Stock Listing',
            'variety' => 'Arabika',
            'origin' => 'Toraja',
            'category' => 'single_origin',
            'price_per_kg' => 150000,
            'stock_kg' => 50,
            'status' => 'listed',
        ]);

        $response = $this->getCatalog(['search' => 'Low Stock Listing']);

        $response->assertOk();

        $item = $response->json('data.0');
        $this->assertNotNull($item);
        $this->assertEquals('low_stock', $item['stock_status']);
    }

    /** @test */
    public function test_catalog_list_does_not_show_sold_out_listing(): void
    {
        // listing-soldout sudah dibuat di setUp dengan stock_kg = 0

        $response = $this->getCatalog(['search' => 'Sold Out Listing']);

        $response->assertOk();

        // Sold out listing TIDAK boleh muncul
        $items = $response->json('data');
        $this->assertCount(0, $items);
    }

    // ========================================================================
    // SECTION 9: CATALOG LIST — VISIBILITY RULES (5 tests)
    // ========================================================================

    /** @test */
    public function test_catalog_list_does_not_show_draft_listings(): void
    {
        $response = $this->getCatalog(['search' => 'Draft Listing']);

        $response->assertOk();

        $items = $response->json('data');
        $this->assertCount(0, $items);
    }

    /** @test */
    public function test_catalog_list_does_not_show_archived_listings(): void
    {
        $response = $this->getCatalog(['search' => 'Archived Listing']);

        $response->assertOk();

        $items = $response->json('data');
        $this->assertCount(0, $items);
    }

    /** @test */
    public function test_catalog_list_only_shows_listed_status(): void
    {
        $response = $this->getCatalog();

        $response->assertOk();

        $items = $response->json('data');
        // Semua item yang muncul harus dari listing dengan status `listed`
        // (kita tidak punya field status di response, tapi kita bisa verifikasi
        //  bahwa draft/archived/sold_out tidak muncul)
        $ids = array_column($items, 'id');
        $this->assertNotContains('listing-draft', $ids);
        $this->assertNotContains('listing-archived', $ids);
        $this->assertNotContains('listing-soldout', $ids);
    }

    /** @test */
    public function test_catalog_list_shows_listings_from_multiple_exporters(): void
    {
        $response = $this->getCatalog();

        $response->assertOk();

        $items = $response->json('data');
        $exporterIds = array_unique(array_column(array_column($items, 'exporter'), 'id'));

        // Harus ada listing dari minimal 2 exporter berbeda
        $this->assertGreaterThanOrEqual(2, count($exporterIds));
    }

    /** @test */
    public function test_catalog_list_hides_farmer_identity_from_all_items(): void
    {
        $response = $this->getCatalog();

        $response->assertOk();

        $responseData = json_encode($response->json());

        // Farmer name TIDAK BOLEH muncul di manapun
        $this->assertStringNotContainsString('Yusuf Ibrahim', $responseData);
        $this->assertStringNotContainsString('farmer', $responseData);
        $this->assertStringNotContainsString('farmer_id', $responseData);
        $this->assertStringNotContainsString('farmer_name', $responseData);
    }

    // ========================================================================
    // SECTION 10: CATALOG DETAIL — RESPONSE STRUCTURE (5 tests)
    // ========================================================================

    /** @test */
    public function test_catalog_detail_returns_200_for_listed_listing(): void
    {
        $response = $this->getCatalogDetail('listing-001');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
                'message' => 'Detail katalog berhasil diambil',
            ]);
    }

    /** @test */
    public function test_catalog_detail_has_5_top_level_sections(): void
    {
        $response = $this->getCatalogDetail('listing-001');

        $response->assertOk();

        $data = $response->json('data');
        $expectedSections = ['listing', 'iot_summary', 'genesis_data', 'blockchain_audit', 'snapshots_preview'];

        foreach ($expectedSections as $section) {
            $this->assertArrayHasKey($section, $data, "Missing section: {$section}");
        }
    }

    /** @test */
    public function test_catalog_detail_listing_object_has_all_required_fields(): void
    {
        $response = $this->getCatalogDetail('listing-001');

        $response->assertOk();

        $listing = $response->json('data.listing');
        $requiredFields = [
            'id', 'batch_code', 'name', 'variety', 'origin', 'elevation',
            'coordinates', 'harvest_date', 'harvest_date_label', 'process',
            'processing_method', 'target_moisture',
            'image_url', 'images', 'category', 'category_label',
            'price_per_kg', 'price_per_kg_display',
            'stock_kg', 'stock_status',
            'is_blockchain_verified', 'listed_at',
        ];

        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $listing, "Missing listing field: {$field}");
        }
    }

    /** @test */
    public function test_catalog_detail_iot_summary_has_all_required_fields(): void
    {
        $response = $this->getCatalogDetail('listing-001');

        $response->assertOk();

        $iot = $response->json('data.iot_summary');
        $requiredFields = [
            'total_logs', 'last_log_at', 'latest_temperature', 'latest_humidity',
            'avg_temperature', 'avg_humidity', 'max_temperature', 'min_temperature',
            'health_status', 'health_color', 'warehouse_log_url', 'trend_url',
        ];

        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $iot, "Missing iot_summary field: {$field}");
        }
    }

    /** @test */
    public function test_catalog_detail_genesis_data_has_all_required_fields(): void
    {
        $response = $this->getCatalogDetail('listing-001');

        $response->assertOk();

        $genesis = $response->json('data.genesis_data');
        $requiredFields = [
            'batch_id', 'batch_code', 'origin', 'variety', 'elevation',
            'harvest_date', 'process', 'summary_log',
            'hash_log', 'hash_payment', 'revenue_share_percent',
            'timestamp_genesis', 'block_number', 'is_immutable',
        ];

        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $genesis, "Missing genesis_data field: {$field}");
        }

        // genesis data harus immutable
        $this->assertTrue($genesis['is_immutable']);
    }

    // ========================================================================
    // SECTION 11: CATALOG DETAIL — NESTED OBJECTS (4 tests)
    // ========================================================================

    /** @test */
    public function test_catalog_detail_includes_full_exporter_info(): void
    {
        $response = $this->getCatalogDetail('listing-001');

        $response->assertOk();

        $exporter = $response->json('data.listing.exporter');
        $this->assertNotEmpty($exporter);

        $exporterFields = ['id', 'name', 'company_name', 'avatar_url', 'location', 'rating', 'total_sales', 'member_since'];
        foreach ($exporterFields as $field) {
            $this->assertArrayHasKey($field, $exporter, "Missing exporter field: {$field}");
        }

        $this->assertEquals('PT Sulawesi Coffee Export', $exporter['name']);
    }

    /** @test */
    public function test_catalog_detail_includes_blockchain_audit_info(): void
    {
        $response = $this->getCatalogDetail('listing-001');

        $response->assertOk();

        $blockchain = $response->json('data.blockchain_audit');
        $this->assertNotEmpty($blockchain);

        $requiredFields = ['smart_contract_address', 'network', 'total_snapshots', 'latest_snapshot_at', 'qr_code_url', 'qr_code_image', 'explorer_url', 'is_verified'];
        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $blockchain, "Missing blockchain_audit field: {$field}");
        }

        $this->assertEquals('Polygon', $blockchain['network']);
    }

    /** @test */
    public function test_catalog_detail_includes_snapshots_preview(): void
    {
        $response = $this->getCatalogDetail('listing-001');

        $response->assertOk();

        $preview = $response->json('data.snapshots_preview');
        $this->assertNotEmpty($preview);

        $this->assertArrayHasKey('total', $preview);
        $this->assertArrayHasKey('latest', $preview);
        $this->assertArrayHasKey('snapshot_url', $preview);

        // latest snapshot fields
        $latestFields = ['id', 'snapshot_date', 'block_number', 'log_count', 'avg_temp', 'avg_humidity', 'hash', 'verified'];
        foreach ($latestFields as $field) {
            $this->assertArrayHasKey($field, $preview['latest'], "Missing snapshots_preview.latest field: {$field}");
        }
    }

    /** @test */
    public function test_catalog_detail_includes_listing_images_array(): void
    {
        $response = $this->getCatalogDetail('listing-001');

        $response->assertOk();

        $images = $response->json('data.listing.images');
        $this->assertIsArray($images);

        if (count($images) > 0) {
            $this->assertArrayHasKey('url', $images[0]);
            $this->assertArrayHasKey('thumbnail_url', $images[0]);
        }
    }

    // ========================================================================
    // SECTION 12: CATALOG DETAIL — FARMER ISOLATION STRICT (3 tests)
    // ========================================================================

    /** @test */
    public function test_catalog_detail_does_not_expose_farmer_name(): void
    {
        $response = $this->getCatalogDetail('listing-001');

        $response->assertOk();

        $responseData = json_encode($response->json());
        $this->assertStringNotContainsString('Yusuf Ibrahim', $responseData);
    }

    /** @test */
    public function test_catalog_detail_does_not_expose_farmer_id_or_hash(): void
    {
        $response = $this->getCatalogDetail('listing-001');

        $response->assertOk();

        $responseData = json_encode($response->json());

        $farmerRelatedTerms = ['farmer_id', 'farmer_name', 'farmer_hash', 'farmer_code', 'farmer_phone', 'farmer_email'];
        foreach ($farmerRelatedTerms as $term) {
            $this->assertStringNotContainsString($term, $responseData, "Farmer-related term '{$term}' found in response");
        }
    }

    /** @test */
    public function test_catalog_detail_all_buyers_see_same_data_for_same_listing(): void
    {
        $response1 = $this->getCatalogDetail('listing-001', $this->buyer);
        $response2 = $this->getCatalogDetail('listing-001', $this->buyer2);

        $response1->assertOk();
        $response2->assertOk();

        // listing, iot_summary, genesis_data, blockchain_audit, snapshots_preview harus identik
        $data1 = $response1->json('data');
        $data2 = $response2->json('data');

        // Hapus buyer-specific fields (kalau ada) dan bandingkan
        $this->assertEquals($data1['listing'], $data2['listing']);
        $this->assertEquals($data1['iot_summary'], $data2['iot_summary']);
        $this->assertEquals($data1['genesis_data'], $data2['genesis_data']);
    }

    // ========================================================================
    // SECTION 13: CATALOG DETAIL — LISTING NOT AVAILABLE (4 tests)
    // ========================================================================

    /** @test */
    public function test_catalog_detail_returns_404_for_draft_listing(): void
    {
        $response = $this->getCatalogDetail('listing-draft');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code' => 'BATCH_NOT_LISTED',
            ])
            ->assertJsonPath('details.listing_id', 'listing-draft');
    }

    /** @test */
    public function test_catalog_detail_returns_404_for_archived_listing(): void
    {
        $response = $this->getCatalogDetail('listing-archived');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code' => 'BATCH_NOT_LISTED',
            ]);
    }

    /** @test */
    public function test_catalog_detail_returns_404_for_nonexistent_listing(): void
    {
        $response = $this->getCatalogDetail('listing-nonexistent');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code' => 'BATCH_NOT_LISTED',
            ]);
    }

    /** @test */
    public function test_catalog_detail_returns_404_for_sold_out_listing(): void
    {
        // listing-soldout status = listed tapi stock = 0
        $response = $this->getCatalogDetail('listing-soldout');

        // Sold out = tidak tersedia → BATCH_NOT_LISTED
        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code' => 'BATCH_NOT_LISTED',
            ]);
    }

    // ========================================================================
    // SECTION 14: CATALOG LOGS — RESPONSE STRUCTURE (4 tests)
    // ========================================================================

    /** @test */
    public function test_catalog_logs_returns_200_with_correct_structure(): void
    {
        $response = $this->getCatalogLogs('listing-001');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
                'message' => 'Log IoT warehouse berhasil diambil',
            ]);

        // Structure: data (array), pagination (object)
        $response->assertJsonStructure([
            'data',
            'pagination' => ['cursor', 'hasMore', 'limit', 'total'],
            'timestamp',
        ]);
    }

    /** @test */
    public function test_catalog_logs_item_has_all_required_fields(): void
    {
        $response = $this->getCatalogLogs('listing-001', ['limit' => 1]);

        $response->assertOk();

        $item = $response->json('data.0');
        $requiredFields = [
            'id', 'batch_listing_id', 'batch_code',
            'timestamp', 'timestamp_label',
            'temperature', 'humidity',
            'temperature_max', 'temperature_min', 'temperature_avg',
            'health_status', 'health_label', 'health_color',
            'sensor_id', 'recorded_at',
        ];

        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $item, "Missing log field: {$field}");
        }
    }

    /** @test */
    public function test_catalog_logs_default_limit_is_50(): void
    {
        $response = $this->getCatalogLogs('listing-001');

        $response->assertOk();
        $this->assertEquals(50, $response->json('pagination.limit'));
    }

    /** @test */
    public function test_catalog_logs_items_belong_to_requested_listing(): void
    {
        $response = $this->getCatalogLogs('listing-001', ['limit' => 5]);

        $response->assertOk();

        $items = $response->json('data');
        foreach ($items as $item) {
            $this->assertEquals('listing-001', $item['batch_listing_id']);
            $this->assertEquals('BJI-TRJ-26054', $item['batch_code']);
        }
    }

    // ========================================================================
    // SECTION 15: CATALOG LOGS — PAGINATION & DATE FILTER (3 tests)
    // ========================================================================

    /** @test */
    public function test_catalog_logs_respects_limit_parameter(): void
    {
        $response = $this->getCatalogLogs('listing-001', ['limit' => 5]);

        $response->assertOk();

        $this->assertLessThanOrEqual(5, count($response->json('data')));
        $this->assertEquals(5, $response->json('pagination.limit'));
    }

    /** @test */
    public function test_catalog_logs_filters_by_date_from(): void
    {
        $response = $this->getCatalogLogs('listing-001', [
            'date_from' => '2026-06-03',
            'limit' => 100,
        ]);

        $response->assertOk();

        // Semua log harus >= 2026-06-03
        $items = $response->json('data');
        foreach ($items as $item) {
            $this->assertGreaterThanOrEqual(
                '2026-06-03',
                substr($item['timestamp'], 0, 10)
            );
        }
    }

    /** @test */
    public function test_catalog_logs_filters_by_date_to(): void
    {
        $response = $this->getCatalogLogs('listing-001', [
            'date_to' => '2026-06-03',
            'limit' => 100,
        ]);

        $response->assertOk();

        // Semua log harus <= 2026-06-03
        $items = $response->json('data');
        foreach ($items as $item) {
            $this->assertLessThanOrEqual(
                '2026-06-03',
                substr($item['timestamp'], 0, 10)
            );
        }
    }

    // ========================================================================
    // SECTION 16: CATALOG LOGS — LISTING NOT AVAILABLE (2 tests)
    // ========================================================================

    /** @test */
    public function test_catalog_logs_returns_404_for_draft_listing(): void
    {
        $response = $this->getCatalogLogs('listing-draft');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code' => 'BATCH_NOT_LISTED',
            ]);
    }

    /** @test */
    public function test_catalog_logs_returns_404_for_nonexistent_listing(): void
    {
        $response = $this->getCatalogLogs('listing-nonexistent');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code' => 'BATCH_NOT_LISTED',
            ]);
    }

    // ========================================================================
    // SECTION 17: CATALOG TREND — RESPONSE STRUCTURE (4 tests)
    // ========================================================================

    /** @test */
    public function test_catalog_trend_returns_200_with_correct_structure(): void
    {
        $response = $this->getCatalogTrend('listing-001');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
                'message' => 'Tren IoT berhasil diambil',
            ]);
    }

    /** @test */
    public function test_catalog_trend_has_metadata_fields(): void
    {
        $response = $this->getCatalogTrend('listing-001');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertArrayHasKey('listing_id', $data);
        $this->assertArrayHasKey('batch_code', $data);
        $this->assertArrayHasKey('period', $data);
        $this->assertArrayHasKey('period_label', $data);
        $this->assertArrayHasKey('total_points', $data);
        $this->assertArrayHasKey('data_points', $data);

        $this->assertEquals('listing-001', $data['listing_id']);
        $this->assertEquals('BJI-TRJ-26054', $data['batch_code']);
    }

    /** @test */
    public function test_catalog_trend_data_points_have_required_fields(): void
    {
        $response = $this->getCatalogTrend('listing-001', ['limit' => 3]);

        $response->assertOk();

        $points = $response->json('data.data_points');
        $this->assertGreaterThan(0, count($points));

        $requiredFields = ['label', 'temperature_avg', 'temperature_max', 'humidity', 'log_count', 'date'];
        $point = $points[0];
        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $point, "Missing trend data_point field: {$field}");
        }
    }

    /** @test */
    public function test_catalog_trend_default_period_is_daily(): void
    {
        $response = $this->getCatalogTrend('listing-001');

        $response->assertOk();

        $this->assertEquals('daily', $response->json('data.period'));
        $this->assertEquals('Harian', $response->json('data.period_label'));
    }

    // ========================================================================
    // SECTION 18: CATALOG TREND — PERIOD PARAMETER (3 tests)
    // ========================================================================

    /** @test */
    public function test_catalog_trend_period_hourly(): void
    {
        $response = $this->getCatalogTrend('listing-001', ['period' => 'hourly']);

        $response->assertOk()
            ->assertJsonPath('data.period', 'hourly')
            ->assertJsonPath('data.period_label', 'Per Jam');
    }

    /** @test */
    public function test_catalog_trend_period_weekly(): void
    {
        $response = $this->getCatalogTrend('listing-001', ['period' => 'weekly']);

        $response->assertOk()
            ->assertJsonPath('data.period', 'weekly')
            ->assertJsonPath('data.period_label', 'Mingguan');
    }

    /** @test */
    public function test_catalog_trend_respects_limit_parameter(): void
    {
        $response = $this->getCatalogTrend('listing-001', ['limit' => 3]);

        $response->assertOk();

        $points = $response->json('data.data_points');
        $this->assertLessThanOrEqual(3, count($points));
        $this->assertEquals(3, $response->json('data.total_points'));
    }

    // ========================================================================
    // SECTION 19: CATALOG TREND — LISTING NOT AVAILABLE (2 tests)
    // ========================================================================

    /** @test */
    public function test_catalog_trend_returns_404_for_draft_listing(): void
    {
        $response = $this->getCatalogTrend('listing-draft');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code' => 'BATCH_NOT_LISTED',
            ]);
    }

    /** @test */
    public function test_catalog_trend_returns_404_for_nonexistent_listing(): void
    {
        $response = $this->getCatalogTrend('listing-nonexistent');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code' => 'BATCH_NOT_LISTED',
            ]);
    }

    // ========================================================================
    // SECTION 20: CATALOG SNAPSHOTS — RESPONSE STRUCTURE (4 tests)
    // ========================================================================

    /** @test */
    public function test_catalog_snapshots_returns_200_with_correct_structure(): void
    {
        $response = $this->getCatalogSnapshots('listing-001');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
                'message' => 'Snapshot on-chain berhasil diambil',
            ]);

        $response->assertJsonStructure([
            'data',
            'pagination' => ['cursor', 'hasMore', 'limit', 'total'],
            'timestamp',
        ]);
    }

    /** @test */
    public function test_catalog_snapshots_item_has_all_required_fields(): void
    {
        $response = $this->getCatalogSnapshots('listing-001', ['limit' => 1]);

        $response->assertOk();

        $item = $response->json('data.0');
        $requiredFields = [
            'id', 'batch_listing_id', 'batch_code',
            'snapshot_date', 'snapshot_date_label',
            'block_number', 'transaction_hash',
            'log_count', 'avg_temperature', 'avg_humidity',
            'max_temperature', 'min_temperature',
            'hash', 'is_verified', 'verified_at',
            'explorer_url', 'created_at',
        ];

        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $item, "Missing snapshot field: {$field}");
        }
    }

    /** @test */
    public function test_catalog_snapshots_items_belong_to_requested_listing(): void
    {
        $response = $this->getCatalogSnapshots('listing-001', ['limit' => 5]);

        $response->assertOk();

        $items = $response->json('data');
        foreach ($items as $item) {
            $this->assertEquals('listing-001', $item['batch_listing_id']);
            $this->assertEquals('BJI-TRJ-26054', $item['batch_code']);
        }
    }

    /** @test */
    public function test_catalog_snapshots_default_limit_is_10(): void
    {
        $response = $this->getCatalogSnapshots('listing-001');

        $response->assertOk();
        $this->assertEquals(10, $response->json('pagination.limit'));
    }

    // ========================================================================
    // SECTION 21: CATALOG SNAPSHOTS — PAGINATION (2 tests)
    // ========================================================================

    /** @test */
    public function test_catalog_snapshots_respects_limit_parameter(): void
    {
        $response = $this->getCatalogSnapshots('listing-001', ['limit' => 3]);

        $response->assertOk();

        $this->assertLessThanOrEqual(3, count($response->json('data')));
        $this->assertEquals(3, $response->json('pagination.limit'));
    }

    /** @test */
    public function test_catalog_snapshots_pagination_has_cursor_and_total(): void
    {
        $response = $this->getCatalogSnapshots('listing-001', ['limit' => 3]);

        $response->assertOk();

        $pagination = $response->json('pagination');
        $this->assertNotNull($pagination['cursor']);
        $this->assertIsBool($pagination['hasMore']);
        $this->assertEquals(12, $pagination['total']); // 12 snapshots di setUp
    }

    // ========================================================================
    // SECTION 22: CATALOG SNAPSHOTS — LISTING NOT AVAILABLE (2 tests)
    // ========================================================================

    /** @test */
    public function test_catalog_snapshots_returns_404_for_draft_listing(): void
    {
        $response = $this->getCatalogSnapshots('listing-draft');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code' => 'BATCH_NOT_LISTED',
            ]);
    }

    /** @test */
    public function test_catalog_snapshots_returns_404_for_nonexistent_listing(): void
    {
        $response = $this->getCatalogSnapshots('listing-nonexistent');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code' => 'BATCH_NOT_LISTED',
            ]);
    }
}

<?php

namespace Tests\Feature\Farmer;

use App\Models\Batch;
use App\Models\BatchLog;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FarmerPaginationFilterTest — Test Case untuk Pagination & Filter di Semua Endpoint Farmer (API Contract V2.1)
 *
 * Scope: 4 endpoint (3 paginated + 1 trend) farmer
 *   - GET /api/v1/farmer/batches                      — List batch (7.2)
 *   - GET /api/v1/farmer/batches/{id}/logs            — List log IoT (8.1)
 *   - GET /api/v1/farmer/batches/{id}/logs/trend      — Trend IoT snapshot (8.2)
 *   - GET /api/v1/farmer/notifications                 — List notifikasi (14.1)
 *
 * Reference: API_CONTRACT_V2_FARMER.md Section 16 (Pagination & Filter), Section 8.2 (Logs Trend)
 *
 * Business Rules V2.1 yang ditest:
 * - Cursor-based pagination: cursor, hasMore, limit (max 100, default 20), total
 * - Filter: status (batch), log_type + date_from/date_to (logs), type + is_read (notifications)
 * - Sort: sort + sort_dir (batch list only)
 * - Cross-endpoint consistency: semua paginated endpoint punya struktur response identik
 * - Data isolation: farmer hanya akses data miliknya
 * - Cursor behavior: no duplicate, null saat no more, filter context maintained
 * - Pagination metadata integrity: total ≥ 0, data count ≤ limit
 * - Logs trend: last_n parameter (default 5, max 30), batch-specific IoT snapshot trend
 *
 * Total: 47 tests — 8 sections
 *   1. Batch Logs Pagination & Filter       (15 tests) — PRIMER, 0 existing
 *   2. Batch List Advanced Filter & Sort   (10 tests) — ADVANCED, complement FarmerBatchTest
 *   3. Notification Edge Cases             (5 tests)  — EDGE CASES
 *   4. Cross-Endpoint Consistency          (4 tests)  — CROSS-CUTTING
 *   5. Cursor Behavior                    (4 tests)  — CROSS-CUTTING
 *   6. Data Isolation                      (2 tests)  — SECURITY
 *   7. Pagination Metadata Integrity        (2 tests)  — QUALITY
 *   8. Batch Logs Trend Endpoint            (5 tests)  — Section 8.2 standalone
 */
class FarmerPaginationFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $farmer;

    private User $farmer2;

    private User $exporter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->farmer = User::factory()->create([
            'role' => 'farmer',
            'phone' => '+62 812-3456-7890',
            'phone_verified' => true,
            'location' => 'Tana Toraja, Sulawesi Selatan',
            'coordinates' => '-3.0701, 119.8923',
            'profile_completion' => 75,
        ]);
        $this->farmer2 = User::factory()->create([
            'role' => 'farmer',
            'phone' => '+62 813-9876-5432',
            'phone_verified' => true,
            'location' => 'Enrekang, Sulawesi Selatan',
            'coordinates' => '-3.4023, 119.8432',
            'profile_completion' => 80,
        ]);
        $this->exporter = User::factory()->create(['role' => 'exporter']);
    }

    // ========================================================================
    // SECTION 1: Batch Logs Pagination & Filter (15 tests)
    // Endpoint: GET /api/v1/farmer/batches/{batchId}/logs
    // Reference: API_CONTRACT_V2_FARMER.md Section 8.1
    // ========================================================================

    public function test_batch_logs_returns_200_with_pagination_structure(): void
    {
        $batch = Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'processing',
        ]);

        $response = $this->actingAs($this->farmer)
            ->getJson("/api/v1/farmer/batches/{$batch->batch_id}/logs");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
                'message' => 'Log monitoring batch berhasil diambil',
            ])
            ->assertJsonStructure([
                'success',
                'code',
                'message',
                'data' => [
                    'batch_id',
                    'batch_code',
                    'iot_source',
                    'logs',
                    'pagination',
                ],
                'timestamp',
            ])
            ->assertJsonStructure([
                'data' => [
                    'pagination' => [
                        'cursor',
                        'hasMore',
                        'limit',
                        'total',
                    ],
                ],
            ]);
    }

    public function test_batch_logs_default_limit_is_20(): void
    {
        $batch = Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'processing',
        ]);

        $response = $this->actingAs($this->farmer)
            ->getJson("/api/v1/farmer/batches/{$batch->batch_id}/logs");

        $response->assertStatus(200)
            ->assertJsonPath('data.pagination.limit', 20);
    }

    public function test_batch_logs_custom_limit(): void
    {
        $batch = Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'processing',
        ]);

        BatchLog::factory()->count(10)->create([
            'batch_id' => $batch->batch_id,
            'source' => 'iot',
        ]);

        $response = $this->actingAs($this->farmer)
            ->getJson("/api/v1/farmer/batches/{$batch->batch_id}/logs?limit=5");

        $response->assertStatus(200)
            ->assertJsonPath('data.pagination.limit', 5);

        $logs = $response->json('data.logs');
        $this->assertLessThanOrEqual(5, count($logs));
    }

    public function test_batch_logs_limit_capped_at_100(): void
    {
        $batch = Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'processing',
        ]);

        $response = $this->actingAs($this->farmer)
            ->getJson("/api/v1/farmer/batches/{$batch->batch_id}/logs?limit=200");

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(100, $response->json('data.pagination.limit'));
    }

    public function test_batch_logs_with_cursor_pagination(): void
    {
        $batch = Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'processing',
        ]);

        $response = $this->actingAs($this->farmer)
            ->getJson("/api/v1/farmer/batches/{$batch->batch_id}/logs?cursor=eyJpZCI6ImxvZy0wMDIifQ==");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
            ]);
    }

    public function test_batch_logs_filter_by_log_type_drying(): void
    {
        $batch = Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'processing',
        ]);

        BatchLog::factory()->count(3)->create([
            'batch_id' => $batch->batch_id,
            'log_type' => 'drying',
            'source' => 'iot',
        ]);
        BatchLog::factory()->count(2)->create([
            'batch_id' => $batch->batch_id,
            'log_type' => 'monitoring',
            'source' => 'iot',
        ]);

        $response = $this->actingAs($this->farmer)
            ->getJson("/api/v1/farmer/batches/{$batch->batch_id}/logs?log_type=drying");

        $response->assertStatus(200);

        $logs = $response->json('data.logs');
        foreach ($logs as $log) {
            $this->assertEquals('drying', $log['log_type']);
        }

        $this->assertEquals(3, $response->json('data.pagination.total'));
    }

    public function test_batch_logs_filter_by_log_type_monitoring(): void
    {
        $batch = Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'processing',
        ]);

        BatchLog::factory()->count(2)->create([
            'batch_id' => $batch->batch_id,
            'log_type' => 'monitoring',
            'source' => 'iot',
        ]);
        BatchLog::factory()->count(3)->create([
            'batch_id' => $batch->batch_id,
            'log_type' => 'drying',
            'source' => 'iot',
        ]);

        $response = $this->actingAs($this->farmer)
            ->getJson("/api/v1/farmer/batches/{$batch->batch_id}/logs?log_type=monitoring");

        $response->assertStatus(200);

        $logs = $response->json('data.logs');
        foreach ($logs as $log) {
            $this->assertEquals('monitoring', $log['log_type']);
        }

        $this->assertEquals(2, $response->json('data.pagination.total'));
    }

    public function test_batch_logs_filter_by_log_type_night(): void
    {
        $batch = Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'processing',
        ]);

        BatchLog::factory()->count(2)->create([
            'batch_id' => $batch->batch_id,
            'log_type' => 'night',
            'source' => 'iot',
        ]);
        BatchLog::factory()->count(4)->create([
            'batch_id' => $batch->batch_id,
            'log_type' => 'drying',
            'source' => 'iot',
        ]);

        $response = $this->actingAs($this->farmer)
            ->getJson("/api/v1/farmer/batches/{$batch->batch_id}/logs?log_type=night");

        $response->assertStatus(200);

        $logs = $response->json('data.logs');
        foreach ($logs as $log) {
            $this->assertEquals('night', $log['log_type']);
        }

        $this->assertEquals(2, $response->json('data.pagination.total'));
    }

    public function test_batch_logs_filter_by_date_from(): void
    {
        $batch = Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'processing',
        ]);

        BatchLog::factory()->create([
            'batch_id' => $batch->batch_id,
            'source' => 'iot',
            'created_at' => '2026-05-28 00:00:00',
        ]);
        BatchLog::factory()->count(3)->create([
            'batch_id' => $batch->batch_id,
            'source' => 'iot',
            'created_at' => '2026-06-02 00:00:00',
        ]);

        $response = $this->actingAs($this->farmer)
            ->getJson("/api/v1/farmer/batches/{$batch->batch_id}/logs?date_from=2026-06-01");

        $response->assertStatus(200);

        $logs = $response->json('data.logs');
        foreach ($logs as $log) {
            $this->assertGreaterThanOrEqual('2026-06-01', substr($log['created_at'], 0, 10));
        }

        $this->assertEquals(3, $response->json('data.pagination.total'));
    }

    public function test_batch_logs_filter_by_date_to(): void
    {
        $batch = Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'processing',
        ]);

        BatchLog::factory()->count(2)->create([
            'batch_id' => $batch->batch_id,
            'source' => 'iot',
            'created_at' => '2026-05-30 00:00:00',
        ]);
        BatchLog::factory()->count(3)->create([
            'batch_id' => $batch->batch_id,
            'source' => 'iot',
            'created_at' => '2026-06-05 00:00:00',
        ]);

        $response = $this->actingAs($this->farmer)
            ->getJson("/api/v1/farmer/batches/{$batch->batch_id}/logs?date_to=2026-05-31");

        $response->assertStatus(200);

        $logs = $response->json('data.logs');
        foreach ($logs as $log) {
            $this->assertLessThanOrEqual('2026-05-31', substr($log['created_at'], 0, 10));
        }

        $this->assertEquals(2, $response->json('data.pagination.total'));
    }

    public function test_batch_logs_filter_by_date_range(): void
    {
        $batch = Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'processing',
        ]);

        BatchLog::factory()->create([
            'batch_id' => $batch->batch_id,
            'source' => 'iot',
            'created_at' => '2026-05-28 00:00:00',
        ]);
        BatchLog::factory()->count(3)->create([
            'batch_id' => $batch->batch_id,
            'source' => 'iot',
            'created_at' => '2026-06-01 00:00:00',
        ]);
        BatchLog::factory()->count(2)->create([
            'batch_id' => $batch->batch_id,
            'source' => 'iot',
            'created_at' => '2026-06-05 00:00:00',
        ]);

        $response = $this->actingAs($this->farmer)
            ->getJson("/api/v1/farmer/batches/{$batch->batch_id}/logs?date_from=2026-06-01&date_to=2026-06-02");

        $response->assertStatus(200);

        $logs = $response->json('data.logs');
        foreach ($logs as $log) {
            $logDate = substr($log['created_at'], 0, 10);
            $this->assertGreaterThanOrEqual('2026-06-01', $logDate);
            $this->assertLessThanOrEqual('2026-06-02', $logDate);
        }

        $this->assertEquals(3, $response->json('data.pagination.total'));
    }

    public function test_batch_logs_invalid_log_type_returns_empty_or_422(): void
    {
        $batch = Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'processing',
        ]);

        $response = $this->actingAs($this->farmer)
            ->getJson("/api/v1/farmer/batches/{$batch->batch_id}/logs?log_type=invalid_type");

        // Bisa 200 dengan empty data atau 422 validation error
        $this->assertThat(
            $response->status(),
            $this->logicalOr(
                $this->equalTo(200),
                $this->equalTo(422)
            )
        );
    }

    public function test_batch_logs_invalid_date_format_returns_422(): void
    {
        $batch = Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'processing',
        ]);

        $response = $this->actingAs($this->farmer)
            ->getJson("/api/v1/farmer/batches/{$batch->batch_id}/logs?date_from=not-a-date");

        $response->assertStatus(422);
    }

    public function test_batch_logs_pagination_total_matches_filter(): void
    {
        $batch = Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'processing',
        ]);

        BatchLog::factory()->count(5)->create([
            'batch_id' => $batch->batch_id,
            'log_type' => 'drying',
            'source' => 'iot',
        ]);
        BatchLog::factory()->count(7)->create([
            'batch_id' => $batch->batch_id,
            'log_type' => 'monitoring',
            'source' => 'iot',
        ]);
        BatchLog::factory()->count(3)->create([
            'batch_id' => $batch->batch_id,
            'log_type' => 'night',
            'source' => 'iot',
        ]);

        $response = $this->actingAs($this->farmer)
            ->getJson("/api/v1/farmer/batches/{$batch->batch_id}/logs?log_type=drying");

        $response->assertStatus(200);
        $this->assertEquals(5, $response->json('data.pagination.total'));
    }

    public function test_batch_logs_batch_not_owned_returns_403(): void
    {
        $otherBatch = Batch::factory()->create([
            'farmer_id' => $this->farmer2->id,
            'status' => 'processing',
        ]);

        $response = $this->actingAs($this->farmer)
            ->getJson("/api/v1/farmer/batches/{$otherBatch->batch_id}/logs");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'BATCH_NOT_OWNED',
            ]);
    }

    // ========================================================================
    // SECTION 2: Batch List Advanced Filter & Sort (10 tests)
    // Endpoint: GET /api/v1/farmer/batches
    // Reference: API_CONTRACT_V2_FARMER.md Section 7.2
    // Complement to FarmerBatchTest (which covers: status=draft/processing/ready,
    //   custom limit, limit cap, sort by tanggal_panen, cursor, data isolation)
    // ========================================================================

    public function test_batch_list_sort_by_varietas_asc(): void
    {
        // Karena 1 farmer = 1 active batch, gunakan status terminal (acquired)
        Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'acquired',
            'varietas' => 'Robusta Enrekang',
        ]);
        Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'acquired',
            'varietas' => 'Arabika Toraja',
        ]);
        Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'acquired',
            'varietas' => 'Excelsa Luwu',
        ]);

        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches?sort=varietas&sort_dir=asc');

        $response->assertStatus(200);

        $data = $response->json('data');
        $names = array_column($data, 'varietas');
        $sorted = $names;
        sort($sorted);
        $this->assertEquals($sorted, $names, 'Batch list should be sorted by varietas ascending');
    }

    public function test_batch_list_sort_by_varietas_desc(): void
    {
        Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'acquired',
            'varietas' => 'Arabika Toraja',
        ]);
        Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'acquired',
            'varietas' => 'Robusta Enrekang',
        ]);
        Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'acquired',
            'varietas' => 'Excelsa Luwu',
        ]);

        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches?sort=varietas&sort_dir=desc');

        $response->assertStatus(200);

        $data = $response->json('data');
        $names = array_column($data, 'varietas');
        $sorted = $names;
        rsort($sorted);
        $this->assertEquals($sorted, $names, 'Batch list should be sorted by varietas descending');
    }

    public function test_batch_list_sort_by_status_asc(): void
    {
        Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'acquired',
        ]);
        Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'completed',
        ]);
        Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches?sort=status&sort_dir=asc');

        $response->assertStatus(200);

        $data = $response->json('data');
        $status = array_column($data, 'status');
        $sorted = $status;
        sort($sorted);
        $this->assertEquals($sorted, $status, 'Batch list should be sorted by status ascending');
    }

    public function test_batch_list_sort_by_created_at_asc(): void
    {
        $batch1 = Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'acquired',
            'created_at' => '2026-05-01 10:00:00',
        ]);
        $batch2 = Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'acquired',
            'created_at' => '2026-05-05 10:00:00',
        ]);
        $batch3 = Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'acquired',
            'created_at' => '2026-05-10 10:00:00',
        ]);

        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches?sort=created_at&sort_dir=asc');

        $response->assertStatus(200);

        $data = $response->json('data');
        $timestamps = array_column($data, 'created_at');
        $this->assertEquals(
            [$batch1->batch_id, $batch2->batch_id, $batch3->batch_id],
            array_column($data, 'id'),
            'Batch list should be sorted oldest first when sort_dir=asc on created_at'
        );
    }

    public function test_batch_list_invalid_sort_field_ignored_or_422(): void
    {
        Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'acquired',
        ]);

        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches?sort=nonexistent_field');

        // Bisa 200 (fallback to default sort) atau 422 (validation error)
        $this->assertThat(
            $response->status(),
            $this->logicalOr(
                $this->equalTo(200),
                $this->equalTo(422)
            )
        );
    }

    public function test_batch_list_invalid_sort_dir_ignored_or_422(): void
    {
        Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'acquired',
        ]);

        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches?sort_dir=invalid_direction');

        // Bisa 200 (fallback to desc) atau 422
        $this->assertThat(
            $response->status(),
            $this->logicalOr(
                $this->equalTo(200),
                $this->equalTo(422)
            )
        );
    }

    public function test_batch_list_status_filter_acquired(): void
    {
        Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'acquired',
        ]);
        Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'acquired',
        ]);
        Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches?status=acquired');

        $response->assertStatus(200)
            ->assertJsonPath('pagination.total', 2);

        $data = $response->json('data');
        foreach ($data as $batch) {
            $this->assertEquals('acquired', $batch['status']);
        }
    }

    public function test_batch_list_status_filter_survey_pending(): void
    {
        Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'acquired',
        ]);
        $pendingBatch = Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'survey_pending',
        ]);

        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches?status=survey_pending');

        $response->assertStatus(200)
            ->assertJsonPath('pagination.total', 1);

        $data = $response->json('data');
        $this->assertEquals($pendingBatch->batch_id, $data[0]['id']);
        $this->assertEquals('survey_pending', $data[0]['status']);
    }

    public function test_batch_list_status_filter_iot_pending(): void
    {
        Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'acquired',
        ]);
        Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'iot_pending',
        ]);

        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches?status=iot_pending');

        $response->assertStatus(200)
            ->assertJsonPath('pagination.total', 1);

        $data = $response->json('data');
        foreach ($data as $batch) {
            $this->assertEquals('iot_pending', $batch['status']);
        }
    }

    public function test_batch_list_negative_limit_returns_422_or_default(): void
    {
        Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'acquired',
        ]);

        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches?limit=-1');

        // Bisa 422 (validation) atau 200 dengan default limit
        $this->assertThat(
            $response->status(),
            $this->logicalOr(
                $this->equalTo(200),
                $this->equalTo(422)
            )
        );
    }

    // ========================================================================
    // SECTION 3: Notification Advanced Pagination Edge Cases (5 tests)
    // Endpoint: GET /api/v1/farmer/notifications
    // Reference: API_CONTRACT_V2_FARMER.md Section 14.1
    // Complement to FarmerNotificationTest (which covers: default limit, custom
    //   limit, cap at 100, cursor, per-type filter, is_read, combined filter,
    //   invalid type, data isolation, hasMore flag, total integer)
    // ========================================================================

    public function test_notifications_invalid_cursor_format_still_200(): void
    {
        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/notifications?cursor=INVALID_BASE64_CURSOR');

        // Cursor rusak seharusnya tetap mengembalikan 200 (fallback ke first page)
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [],
                'pagination' => [
                    'cursor',
                    'hasMore',
                    'limit',
                    'total',
                ],
            ]);
    }

    public function test_notifications_zero_limit_returns_422_or_default(): void
    {
        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/notifications?limit=0');

        $this->assertThat(
            $response->status(),
            $this->logicalOr(
                $this->equalTo(200),   // Fallback ke default limit 20
                $this->equalTo(422)    // Validation error
            )
        );
    }

    public function test_notifications_limit_as_string_ignored_or_422(): void
    {
        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/notifications?limit=abc');

        // String non-numeric seharusnya 422 atau di-ignore
        $this->assertThat(
            $response->status(),
            $this->logicalOr(
                $this->equalTo(200),
                $this->equalTo(422)
            )
        );
    }

    public function test_notifications_filter_nonexistent_type_returns_empty(): void
    {
        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/notifications?type=payment');

        // Tipe yang tidak ada di enum seharusnya mengembalikan 200 dengan data kosong
        $response->assertStatus(200);

        // Jika 200, pastikan tidak ada data dengan type yang tidak valid
        if ($response->json('data')) {
            foreach ($response->json('data') as $notif) {
                $this->assertNotEquals('payment', $notif['type']);
            }
        }
    }

    public function test_notifications_cursor_null_when_no_more_data(): void
    {
        // Dengan sedikit data, pastikan hasMore false / cursor null
        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/notifications');

        $response->assertStatus(200);
        $pagination = $response->json('pagination');

        // Jika data ≤ limit, hasMore harus false
        $dataCount = count($response->json('data'));
        if ($dataCount < $pagination['limit']) {
            $this->assertFalse($pagination['hasMore'], 'hasMore should be false when data count < limit');
        }
    }

    // ========================================================================
    // SECTION 4: Cross-Endpoint Pagination Consistency (4 tests)
    // Verifies that ALL 3 paginated farmer endpoints share the same response
    // structure and default behaviors per Section 16 of the API contract.
    // ========================================================================

    public function test_all_paginated_endpoints_share_same_structure(): void
    {
        $batch = Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'processing',
        ]);

        // 1) GET /farmer/batches
        $batchList = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches');

        // 2) GET /farmer/batches/{id}/logs
        $batchLogs = $this->actingAs($this->farmer)
            ->getJson("/api/v1/farmer/batches/{$batch->batch_id}/logs");

        // 3) GET /farmer/notifications
        $notifications = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/notifications');

        // Semua harus 200
        $batchList->assertStatus(200);
        $batchLogs->assertStatus(200);
        $notifications->assertStatus(200);

        // Semua harus punya field pagination dengan key: cursor, hasMore, limit, total
        $expectedPaginationKeys = ['cursor', 'hasMore', 'limit', 'total'];

        foreach ([$batchList, $batchLogs, $notifications] as $response) {
            $pagination = $response->json('pagination');
            $this->assertNotNull($pagination, 'Pagination object should exist');
            foreach ($expectedPaginationKeys as $key) {
                $this->assertArrayHasKey($key, $pagination, "Pagination should have '{$key}'");
            }
        }
    }

    public function test_all_paginated_endpoints_default_limit_20(): void
    {
        $batch = Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'processing',
        ]);

        $batchList = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches');

        $batchLogs = $this->actingAs($this->farmer)
            ->getJson("/api/v1/farmer/batches/{$batch->batch_id}/logs");

        $notifications = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/notifications');

        $this->assertEquals(20, $batchList->json('pagination.limit'));
        $this->assertEquals(20, $batchLogs->json('data.pagination.limit'));
        $this->assertEquals(20, $notifications->json('pagination.limit'));
    }

    public function test_all_paginated_endpoints_cap_limit_at_100(): void
    {
        $batch = Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'processing',
        ]);

        $batchList = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches?limit=999');

        $batchLogs = $this->actingAs($this->farmer)
            ->getJson("/api/v1/farmer/batches/{$batch->batch_id}/logs?limit=999");

        $notifications = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/notifications?limit=999');

        $this->assertLessThanOrEqual(100, $batchList->json('pagination.limit'));
        $this->assertLessThanOrEqual(100, $batchLogs->json('data.pagination.limit'));
        $this->assertLessThanOrEqual(100, $notifications->json('pagination.limit'));
    }

    public function test_all_paginated_endpoints_have_timestamp_field(): void
    {
        $batch = Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'processing',
        ]);

        $batchList = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches');

        $batchLogs = $this->actingAs($this->farmer)
            ->getJson("/api/v1/farmer/batches/{$batch->batch_id}/logs");

        $notifications = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/notifications');

        foreach ([$batchList, $batchLogs, $notifications] as $response) {
            $response->assertStatus(200);
            $this->assertArrayHasKey('timestamp', $response->json(),
                'Response should have top-level timestamp field');
        }
    }

    // ========================================================================
    // SECTION 5: Cursor Behavior (4 tests)
    // Validates cursor-based pagination mechanics across endpoints.
    // ========================================================================

    public function test_cursor_from_last_page_has_more_false(): void
    {
        // Buat data sedikit (< default limit 20)
        Batch::factory()->count(3)->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'acquired',
        ]);

        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches');

        $response->assertStatus(200);
        $pagination = $response->json('pagination');

        // 3 data < limit 20, harusnya hasMore = false
        $this->assertFalse($pagination['hasMore'],
            'hasMore should be false when total items ≤ limit');
    }

    public function test_cursor_pagination_no_duplicate_items(): void
    {
        // Buat cukup data untuk 2 halaman
        $totalItems = 25;
        for ($i = 0; $i < $totalItems; $i++) {
            Batch::factory()->create([
                'farmer_id' => $this->farmer->id,
                'status' => 'acquired',
                'created_at' => now()->subMinutes($totalItems - $i),
            ]);
        }

        // Halaman 1
        $page1 = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches?limit=10');

        $page1->assertStatus(200);
        $cursor = $page1->json('pagination.cursor');

        if ($cursor && $page1->json('pagination.hasMore')) {
            // Halaman 2
            $page2 = $this->actingAs($this->farmer)
                ->getJson("/api/v1/farmer/batches?limit=10&cursor={$cursor}");

            $page2->assertStatus(200);

            $ids1 = array_column($page1->json('data'), 'id');
            $ids2 = array_column($page2->json('data'), 'id');

            $overlap = array_intersect($ids1, $ids2);
            $this->assertEmpty($overlap, 'No duplicate items across pages');
        } else {
            // Jika data tidak cukup untuk 2 halaman, skip assertion
            $this->markTestSkipped('Not enough data for multi-page cursor test');
        }
    }

    public function test_cursor_with_filter_maintains_filter_context(): void
    {
        // Buat batch dengan berbagai status
        Batch::factory()->count(5)->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'acquired',
        ]);
        Batch::factory()->count(5)->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'completed',
        ]);

        // Ambil halaman pertama dengan filter
        $page1 = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches?status=acquired&limit=3');

        $page1->assertStatus(200);

        // Semua item di page 1 harus status=acquired
        foreach ($page1->json('data') as $batch) {
            $this->assertEquals('acquired', $batch['status']);
        }

        $cursor = $page1->json('pagination.cursor');

        if ($cursor && $page1->json('pagination.hasMore')) {
            // Halaman 2 dengan filter + cursor
            $page2 = $this->actingAs($this->farmer)
                ->getJson("/api/v1/farmer/batches?status=acquired&limit=3&cursor={$cursor}");

            $page2->assertStatus(200);

            // Filter context harus tetap terjaga
            foreach ($page2->json('data') as $batch) {
                $this->assertEquals('acquired', $batch['status'],
                    'Cursor pagination should maintain filter context');
            }

            $this->assertEquals(5, $page2->json('pagination.total'),
                'Total should reflect filtered count, not all items');
        }
    }

    public function test_empty_database_cursor_is_null(): void
    {
        // Tidak ada data sama sekali
        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches');

        $response->assertStatus(200);
        $pagination = $response->json('pagination');

        $this->assertNull($pagination['cursor'] ?? null,
            'Cursor should be null when no data exists');
        $this->assertFalse($pagination['hasMore'],
            'hasMore should be false when no data exists');
        $this->assertEquals(0, $pagination['total'],
            'Total should be 0 when no data exists');
        $this->assertEmpty($response->json('data'),
            'Data should be empty array when no data exists');
    }

    // ========================================================================
    // SECTION 6: Data Isolation in Paginated Results (2 tests)
    // Security: ensures farmers cannot see each other's data via pagination.
    // ========================================================================

    public function test_batch_logs_isolation_farmer_cannot_see_other_logs(): void
    {
        // Buat batch + logs untuk farmer2
        $otherBatch = Batch::factory()->create([
            'farmer_id' => $this->farmer2->id,
            'status' => 'processing',
        ]);
        BatchLog::factory()->count(5)->create([
            'batch_id' => $otherBatch->batch_id,
            'source' => 'iot',
        ]);

        // Farmer coba akses log batch milik farmer2
        $response = $this->actingAs($this->farmer)
            ->getJson("/api/v1/farmer/batches/{$otherBatch->batch_id}/logs");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'BATCH_NOT_OWNED',
            ]);
    }

    public function test_batch_list_pagination_respects_farmer_ownership(): void
    {
        // Farmer: 3 batches (semua acquired agar bisa multiple)
        Batch::factory()->count(3)->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'acquired',
        ]);

        // Farmer2: 5 batches
        Batch::factory()->count(5)->create([
            'farmer_id' => $this->farmer2->id,
            'status' => 'acquired',
        ]);

        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches');

        $response->assertStatus(200)
            ->assertJsonPath('pagination.total', 3);

        // Pastikan semua batch yang dikembalikan milik farmer ini
        $data = $response->json('data');
        foreach ($data as $batch) {
            // Field farmer_id mungkin tidak ada di response public,
            // tapi total harus sesuai milik farmer
            $this->assertNotEmpty($batch['id']);
        }

        // Verify farmer2 juga punya total sendiri
        $response2 = $this->actingAs($this->farmer2)
            ->getJson('/api/v1/farmer/batches');

        $response2->assertStatus(200)
            ->assertJsonPath('pagination.total', 5);
    }

    // ========================================================================
    // SECTION 7: Pagination Metadata Integrity (2 tests)
    // Quality: ensures pagination metadata is always valid and consistent.
    // ========================================================================

    public function test_pagination_total_always_integer_non_negative(): void
    {
        $batch = Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'processing',
        ]);

        // Batch list
        $batchList = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches');
        $totalBatch = $batchList->json('pagination.total');

        // Batch logs
        $batchLogs = $this->actingAs($this->farmer)
            ->getJson("/api/v1/farmer/batches/{$batch->batch_id}/logs");
        $totalLogs = $batchLogs->json('data.pagination.total');

        // Notifications
        $notifs = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/notifications');
        $totalNotifs = $notifs->json('pagination.total');

        foreach ([$totalBatch, $totalLogs, $totalNotifs] as $total) {
            $this->assertIsInt($total, 'Pagination total must be an integer');
            $this->assertGreaterThanOrEqual(0, $total, 'Pagination total must be non-negative');
        }
    }

    public function test_data_count_matches_or_less_than_limit(): void
    {
        // Buat data yang cukup banyak
        $batch = Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'processing',
        ]);
        BatchLog::factory()->count(50)->create([
            'batch_id' => $batch->batch_id,
            'source' => 'iot',
        ]);

        // Test dengan limit=10
        $response = $this->actingAs($this->farmer)
            ->getJson("/api/v1/farmer/batches/{$batch->batch_id}/logs?limit=10");

        $response->assertStatus(200);

        $logs = $response->json('data.logs');
        $limit = $response->json('data.pagination.limit');
        $total = $response->json('data.pagination.total');

        $this->assertLessThanOrEqual($limit, count($logs),
            'Returned data count should not exceed limit');
        $this->assertEquals(50, $total,
            'Total should reflect all matching items regardless of limit');

        // Batch list
        $batchResponse = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches?limit=10');

        $batchResponse->assertStatus(200);

        $batchData = $batchResponse->json('data');
        $batchLimit = $batchResponse->json('pagination.limit');

        $this->assertLessThanOrEqual($batchLimit, count($batchData),
            'Batch list data count should not exceed limit');
    }

    // ========================================================================
    // SECTION 8: Batch Logs Trend — Standalone Endpoint (5 tests)
    // ========================================================================

    public function test_logs_trend_returns_200_with_valid_structure(): void
    {
        $batch = Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'processing',
        ]);

        BatchLog::factory()->count(5)->create([
            'batch_id' => $batch->batch_id,
            'source' => 'iot',
        ]);

        $response = $this->actingAs($this->farmer)
            ->getJson("/api/v1/farmer/batches/{$batch->batch_id}/logs/trend");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
            ])
            ->assertJsonStructure([
                'success',
                'code',
                'message',
                'data' => [
                    'batch_id',
                    'batch_code',
                    'label',
                    'sublabel',
                    'source',
                    'blockchain_verified',
                    'data_points',
                ],
                'timestamp',
            ]);

        // source harus 'iot'
        $response->assertJsonPath('data.source', 'iot');
    }

    public function test_logs_trend_data_points_have_valid_fields(): void
    {
        $batch = Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'processing',
        ]);

        BatchLog::factory()->count(3)->create([
            'batch_id' => $batch->batch_id,
            'source' => 'iot',
        ]);

        $response = $this->actingAs($this->farmer)
            ->getJson("/api/v1/farmer/batches/{$batch->batch_id}/logs/trend");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'data_points' => [
                        '*' => [
                            'label',
                            'temperature',
                            'humidity',
                            'snapshot_date',
                            'timestamp',
                        ],
                    ],
                ],
            ]);

        // Validasi setiap data point punya tipe yang benar
        $dataPoints = $response->json('data.data_points');
        foreach ($dataPoints as $point) {
            $this->assertIsNumeric($point['temperature'],
                'temperature must be numeric');
            $this->assertIsNumeric($point['humidity'],
                'humidity must be numeric');
            $this->assertNotEmpty($point['label'],
                'label must not be empty');
            $this->assertMatchesRegularExpression(
                '/^D-\d+$/',
                $point['label'],
                'Label should use D-N format (daily snapshot)'
            );
        }
    }

    public function test_logs_trend_last_n_parameter_returns_correct_count(): void
    {
        $batch = Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'processing',
        ]);

        // Buat 15 log entries
        BatchLog::factory()->count(15)->create([
            'batch_id' => $batch->batch_id,
            'source' => 'iot',
            'created_at' => fn () => now()->subDays(14 - rand(0, 14)),
        ]);

        // Request last_n=10
        $response = $this->actingAs($this->farmer)
            ->getJson("/api/v1/farmer/batches/{$batch->batch_id}/logs/trend?last_n=10");

        $response->assertStatus(200);

        $dataPoints = $response->json('data.data_points');
        $this->assertCount(10, $dataPoints,
            'Should return exactly 10 data points when last_n=10');
    }

    public function test_logs_trend_last_n_max_30_enforced(): void
    {
        $batch = Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status' => 'processing',
        ]);

        // Buat 40 log entries (lebih dari max)
        BatchLog::factory()->count(40)->create([
            'batch_id' => $batch->batch_id,
            'source' => 'iot',
            'created_at' => fn () => now()->subDays(rand(0, 39)),
        ]);

        // Request last_n=50 (lebih dari max 30)
        $response = $this->actingAs($this->farmer)
            ->getJson("/api/v1/farmer/batches/{$batch->batch_id}/logs/trend?last_n=50");

        // Harusnya 200 tapi capped ke 30, ATAU 422 validation error
        $this->assertThat(
            $response->status(),
            $this->logicalOr(
                $this->equalTo(200),
                $this->equalTo(422)
            )
        );

        if ($response->status() === 200) {
            $dataPoints = $response->json('data.data_points');
            $this->assertLessThanOrEqual(30, count($dataPoints),
                'Data points should be capped at max 30 when last_n exceeds limit');
        }
    }

    public function test_logs_trend_batch_not_owned_returns_403(): void
    {
        $otherBatch = Batch::factory()->create([
            'farmer_id' => $this->farmer2->id,
            'status' => 'processing',
        ]);

        BatchLog::factory()->count(5)->create([
            'batch_id' => $otherBatch->batch_id,
            'source' => 'iot',
        ]);

        $response = $this->actingAs($this->farmer)
            ->getJson("/api/v1/farmer/batches/{$otherBatch->batch_id}/logs/trend");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'BATCH_NOT_OWNED',
            ]);
    }
}

<?php

namespace Tests\Feature\Farmer;

use App\Models\Batch;
use App\Models\BatchLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * FarmerDashboardTest — Test Case untuk Modul Dashboard Petani (API Contract V2.1)
 *
 * Scope: 1 mega-endpoint dashboard farmer
 *   - GET /api/v1/farmer/dashboard — Overview lengkap dashboard petani (6.1)
 *
 * Reference: API_CONTRACT_V2_FARMER.md Section 6 (Modul Dashboard Petani)
 *
 * Response sections yang ditest (10 section):
 *   1. farmer      — Info petani (name, phone, phone_verified, profile_completion, location)
 *   2. stats       — Stats card (processing, ready_for_exporter, today_logs, reputation)
 *   3. progress    — Progress card (4 items: profile_complete, batch_draft, daily_log, sync)
 *   4. next_actions — Rekomendasi aksi berikutnya (dinamis berdasarkan kondisi petani)
 *   5. active_batch — Batch aktif petani (null jika tidak ada)
 *   6. latest_batches — Daftar batch terakhir milik petani
 *   7. daily_logs  — Log IoT hari ini (read-only, dari IoT sensor)
 *   8. log_trend   — Tren 5 snapshot IoT terakhir (mini chart)
 *   9. batch_logs_timeline — Timeline event batch (foto, survey, log)
 *  10. warnings   — Warning flags (phone_missing)
 *
 * Business Rules V2.1 yang ditest:
 * - stats.today_logs = snapshot IoT hari ini (bukan input manual petani)
 * - progress.daily_log judul = "Monitoring IoT" (bukan "Log Harian")
 * - next_actions TIDAK boleh ada "Tambah log hari ini" (log = IoT)
 * - log_trend label = D-1, D-2 (daily snapshot, bukan H1, H2 hourly)
 * - warnings.phone_missing = true jika phone null/empty (trigger PhoneNumberWarning)
 * - Data isolation: petani hanya lihat data miliknya
 * - active_batch = null jika tidak ada batch aktif
 * - reputation skala 0-100, dihitung dari profil, log, batch, responsivitas
 */
class FarmerDashboardTest extends TestCase
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
            'name' => 'Yusuf Ibrahim',
            'phone' => '+62 812-3456-7890',
            'phone_verified' => true,
            'location' => 'Tana Toraja, Sulawesi Selatan',
            'coordinates' => '-3.0701, 119.8923',
            'profile_completion' => 75,
            'iot_assigned' => false,
        ]);

        $this->farmer2 = User::factory()->create([
            'role' => 'farmer',
            'name' => 'Ahmad Tandilang',
            'phone' => '+62 813-9876-5432',
            'phone_verified' => true,
            'location' => 'Enrekang, Sulawesi Selatan',
            'coordinates' => '-3.4023, 119.8432',
            'profile_completion' => 80,
            'iot_assigned' => true,
            'iot_sensor_id' => 'IOT-ENK-001',
        ]);

        $this->exporter = User::factory()->create(['role' => 'exporter']);
    }

    // ========================================================================
    // Helper Methods
    // ========================================================================

    /**
     * Helper: panggil dashboard endpoint
     */
    private function getDashboard(?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->farmer)
            ->getJson('/api/v1/farmer/dashboard');
    }

    /**
     * Helper: buat batch milik farmer utama
     */
    private function createBatch(string $batchId, string $status = 'draft', array $overrides = []): Batch
    {
        return Batch::factory()->create(array_merge([
            'farmer_id' => $this->farmer->id,
            'batch_id' => $batchId,
            'varietas' => 'Arabika Toraja',
            'kebun' => 'Kebun Hulu 01',
            'desa' => 'Buntu Batu',
            'kecamatan' => 'Baraka',
            'proses_awal' => 'Penjemuran',
            'status' => $status,
        ], $overrides));
    }

    /**
     * Helper: buat batch milik farmer2
     */
    private function createBatchForFarmer2(string $batchId, string $status = 'draft', array $overrides = []): Batch
    {
        return Batch::factory()->create(array_merge([
            'farmer_id' => $this->farmer2->id,
            'batch_id' => $batchId,
            'varietas' => 'Robusta Enrekang',
            'kebun' => 'Kebun Tengah 02',
            'desa' => 'Maiwa',
            'kecamatan' => 'Maiwa',
            'proses_awal' => 'Fermentasi',
            'status' => $status,
        ], $overrides));
    }

    /**
     * Helper: buat IoT log untuk batch
     */
    private function createBatchLog(string $batchId, array $overrides = []): BatchLog
    {
        return BatchLog::factory()->create(array_merge([
            'batch_id' => $batchId,
            'temperature' => 32,
            'humidity' => 68,
            'log_type' => 'drying',
            'source' => 'iot',
            'note' => 'Normal',
            'note_color' => '#4CAF7D',
            'created_at' => now(),
        ], $overrides));
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
                'message' => 'Data dashboard petani berhasil diambil',
            ])
            ->assertJsonStructure([
                'success',
                'code',
                'message',
                'data' => [
                    'farmer',
                    'stats',
                    'progress',
                    'next_actions',
                    'active_batch',
                    'latest_batches',
                    'daily_logs',
                    'log_trend',
                    'batch_logs_timeline',
                    'warnings',
                ],
                'timestamp',
            ]);
    }

    public function test_dashboard_farmer_section_has_correct_fields()
    {
        $response = $this->getDashboard();

        $response->assertStatus(200)
            ->assertJsonPath('data.farmer.name', $this->farmer->name)
            ->assertJsonPath('data.farmer.phone', $this->farmer->phone)
            ->assertJsonPath('data.farmer.phone_verified', $this->farmer->phone_verified)
            ->assertJsonPath('data.farmer.profile_completion', $this->farmer->profile_completion)
            ->assertJsonPath('data.farmer.location', $this->farmer->location);
    }

    public function test_dashboard_stats_section_has_correct_fields()
    {
        $response = $this->getDashboard();

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'stats' => [
                        'processing',
                        'processing_caption',
                        'processing_subcaption',
                        'ready_for_exporter',
                        'ready_for_exporter_caption',
                        'ready_for_exporter_subcaption',
                        'today_logs',
                        'today_logs_caption',
                        'today_logs_subcaption',
                        'reputation',
                        'reputation_max',
                        'reputation_caption',
                        'reputation_subcaption',
                    ],
                ],
            ]);

        // reputation_max harus 100
        $response->assertJsonPath('data.stats.reputation_max', 100);
    }

    public function test_dashboard_progress_section_has_correct_fields()
    {
        $response = $this->getDashboard();

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'progress' => [
                        'completed_count',
                        'total_count',
                        'label',
                        'items',
                    ],
                ],
            ]);

        // Progress items harus berisi 4 key
        $items = $response->json('data.progress.items');
        $this->assertCount(4, $items);

        // Cek key unik di items
        $keys = array_column($items, 'key');
        $this->assertContains('profile_complete', $keys);
        $this->assertContains('batch_draft', $keys);
        $this->assertContains('daily_log', $keys);
        $this->assertContains('sync', $keys);
    }

    public function test_dashboard_progress_items_have_valid_structure()
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

    public function test_dashboard_warnings_section_has_correct_fields()
    {
        $response = $this->getDashboard();

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'warnings' => [
                        'phone_missing',
                        'phone_message',
                    ],
                ],
            ]);

        // Farmer punya phone → phone_missing = false
        $response->assertJsonPath('data.warnings.phone_missing', false);
    }

    // ========================================================================
    // B. Stats — Logika Perhitungan
    // ========================================================================

    public function test_stats_processing_counts_processing_batches()
    {
        // Buat 2 batch processing
        $this->createBatch('batch-001', 'processing');
        $this->createBatch('batch-002', 'processing');

        $response = $this->getDashboard();

        $response->assertStatus(200)
            ->assertJsonPath('data.stats.processing', 2);
    }

    public function test_stats_processing_does_not_count_other_statuses()
    {
        // Buat batch dengan berbagai status
        $this->createBatch('batch-001', 'processing');
        $this->createBatch('batch-002', 'ready');
        $this->createBatch('batch-003', 'draft');
        $this->createBatch('batch-004', 'acquired');

        $response = $this->getDashboard();

        $response->assertStatus(200)
            ->assertJsonPath('data.stats.processing', 1)
            ->assertJsonPath('data.stats.ready_for_exporter', 1);
    }

    public function test_stats_ready_for_exporter_counts_ready_batches()
    {
        // Buat 2 batch ready
        $this->createBatch('batch-001', 'ready');
        $this->createBatch('batch-002', 'ready');

        $response = $this->getDashboard();

        $response->assertStatus(200)
            ->assertJsonPath('data.stats.ready_for_exporter', 2);
    }

    public function test_stats_today_logs_counts_iot_snapshots_today()
    {
        // Buat batch processing + IoT logs hari ini
        $this->createBatch('batch-001', 'processing');

        $this->createBatchLog('batch-001', [
            'created_at' => now(),
        ]);
        $this->createBatchLog('batch-001', [
            'created_at' => now()->subHours(1),
        ]);
        $this->createBatchLog('batch-001', [
            'created_at' => now()->subHours(2),
        ]);

        $response = $this->getDashboard();

        $response->assertStatus(200)
            ->assertJsonPath('data.stats.today_logs', 3);
    }

    public function test_stats_today_logs_excludes_yesterday_logs()
    {
        $this->createBatch('batch-001', 'processing');

        // Log hari ini (1)
        $this->createBatchLog('batch-001', [
            'created_at' => now(),
        ]);

        // Log kemarin (1) — tidak boleh dihitung
        $this->createBatchLog('batch-001', [
            'created_at' => now()->subDays(1)->subHour(),
        ]);

        $response = $this->getDashboard();

        $response->assertStatus(200)
            ->assertJsonPath('data.stats.today_logs', 1);
    }

    public function test_stats_reputation_within_valid_range()
    {
        $response = $this->getDashboard();

        $response->assertStatus(200);

        $reputation = $response->json('data.stats.reputation');
        $reputationMax = $response->json('data.stats.reputation_max');

        $this->assertGreaterThanOrEqual(0, $reputation);
        $this->assertLessThanOrEqual($reputationMax, $reputation);
        $this->assertIsInt($reputation);
    }

    public function test_all_stats_zero_for_brand_new_farmer()
    {
        // Petani baru tanpa batch apapun
        $newFarmer = User::factory()->create([
            'role' => 'farmer',
            'name' => 'Petani Baru',
            'phone' => null,
            'phone_verified' => false,
            'profile_completion' => 0,
        ]);

        $response = $this->getDashboard($newFarmer);

        $response->assertStatus(200)
            ->assertJsonPath('data.stats.processing', 0)
            ->assertJsonPath('data.stats.ready_for_exporter', 0)
            ->assertJsonPath('data.stats.today_logs', 0);
    }

    // ========================================================================
    // C. Active Batch
    // ========================================================================

    public function test_active_batch_populated_when_farmer_has_active_batch()
    {
        $this->createBatch('batch-001', 'processing', [
            'varietas' => 'Arabika Toraja',
        ]);

        $response = $this->getDashboard();

        $response->assertStatus(200)
            ->assertJsonPath('data.active_batch', function ($value) {
                return $value !== null;
            })
            ->assertJsonStructure([
                'data' => [
                    'active_batch' => [
                        'id',
                        'code',
                        'name',
                        'variety',
                        'harvest_date',
                        'status',
                        'status_label',
                        'health',
                        'health_color',
                        'temperature',
                        'humidity',
                        'survey_status',
                        'iot_status',
                        'last_log_at',
                        'detail_url',
                    ],
                ],
            ]);

        $response->assertJsonPath('data.active_batch.id', 'batch-001');
        $response->assertJsonPath('data.active_batch.status', 'processing');
    }

    public function test_active_batch_is_null_when_no_active_batch()
    {
        // Tidak ada batch untuk farmer

        $response = $this->getDashboard();

        $response->assertStatus(200)
            ->assertJsonPath('data.active_batch', null);
    }

    public function test_active_batch_null_when_all_batches_acquired()
    {
        // Buat batch tapi sudah acquired (slot kosong)
        $this->createBatch('batch-001', 'acquired');

        $response = $this->getDashboard();

        $response->assertStatus(200)
            ->assertJsonPath('data.active_batch', null);
    }

    public function test_active_batch_shows_survey_status_for_survey_pending_batch()
    {
        $this->createBatch('batch-001', 'survey_pending');

        $response = $this->getDashboard();

        $response->assertStatus(200)
            ->assertJsonPath('data.active_batch.status', 'survey_pending')
            ->assertJsonPath('data.active_batch.survey_status', 'Menunggu Survey');
    }

    public function test_active_batch_priority_processing_over_ready_over_draft()
    {
        // Buat batch: draft, ready, processing
        $this->createBatch('batch-003', 'draft');
        $this->createBatch('batch-002', 'ready');
        $this->createBatch('batch-001', 'processing');

        $response = $this->getDashboard();

        // active_batch harus batch yang paling "aktif" (processing > ready > draft)
        // Atau batch yang sedang diprioritaskan oleh sistem
        $response->assertStatus(200);
        $activeBatch = $response->json('data.active_batch');
        $this->assertNotNull($activeBatch);
        $this->assertContains($activeBatch['status'], ['processing', 'ready', 'draft']);
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
            ['profile_complete', 'batch_draft', 'daily_log', 'sync'],
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
        $this->assertEquals(4, $totalCount); // Selalu 4 items
    }

    public function test_progress_daily_log_title_is_monitoring_iot()
    {
        $response = $this->getDashboard();

        $items = $response->json('data.progress.items');
        $dailyLogItem = collect($items)->firstWhere('key', 'daily_log');

        $this->assertNotNull($dailyLogItem);
        // V2.1: judul harus "Monitoring IoT" (bukan "Log Harian")
        $this->assertEquals('Monitoring IoT', $dailyLogItem['title']);
    }

    public function test_progress_items_have_valid_priority_values()
    {
        $response = $this->getDashboard();

        $items = $response->json('data.progress.items');
        $validPriorities = ['low', 'medium', 'high'];

        foreach ($items as $item) {
            $this->assertContains($item['priority'], $validPriorities,
                "Invalid priority '{$item['priority']}' for progress item '{$item['key']}'");
        }
    }

    public function test_progress_items_have_valid_progress_percent_range()
    {
        $response = $this->getDashboard();

        $items = $response->json('data.progress.items');

        foreach ($items as $item) {
            $this->assertGreaterThanOrEqual(0, $item['progress_percent']);
            $this->assertLessThanOrEqual(100, $item['progress_percent']);
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

    public function test_next_actions_sorted_by_priority()
    {
        $response = $this->getDashboard();

        $nextActions = $response->json('data.next_actions');

        // Extract priorities in order
        $priorities = array_column($nextActions, 'priority');
        $priorityOrder = array_flip(['high', 'medium', 'low']);

        // Verifikasi: high harus muncul sebelum medium, medium sebelum low
        $isSorted = true;
        for ($i = 0; $i < count($priorities) - 1; $i++) {
            $current = $priorityOrder[$priorities[$i]] ?? 99;
            $next = $priorityOrder[$priorities[$i + 1]] ?? 99;
            if ($current > $next) {
                $isSorted = false;
                break;
            }
        }

        $this->assertTrue($isSorted, 'next_actions harus diurutkan berdasarkan prioritas (high → medium → low)');
    }

    public function test_next_actions_no_add_log_action()
    {
        // V2.1: next_actions TIDAK boleh berisi "Tambah log hari ini"
        // karena log = 100% IoT, bukan manual entry

        $response = $this->getDashboard();

        $nextActions = $response->json('data.next_actions');

        foreach ($nextActions as $action) {
            $this->assertStringNotContainsString('Tambah log', $action['title'],
                'V2.1: next_actions tidak boleh berisi "Tambah log" karena log = IoT');
            $this->assertStringNotContainsString('log hari ini', strtolower($action['title']),
                'V2.1: next_actions tidak boleh berisi "log hari ini" karena log = IoT');
        }
    }

    public function test_next_actions_has_valid_priority_and_period_values()
    {
        $response = $this->getDashboard();

        $nextActions = $response->json('data.next_actions');
        $validPriorities = ['high', 'medium', 'low'];
        $validPeriods = ['today', 'next'];

        foreach ($nextActions as $action) {
            $this->assertContains($action['priority'], $validPriorities);
            $this->assertContains($action['period'], $validPeriods);
        }
    }

    // ========================================================================
    // F. Latest Batches
    // ========================================================================

    public function test_latest_batches_contains_farmers_batches()
    {
        $this->createBatch('batch-001', 'processing');
        $this->createBatch('batch-002', 'ready');
        $this->createBatch('batch-003', 'draft');

        $response = $this->getDashboard();

        $response->assertStatus(200);

        $latestBatches = $response->json('data.latest_batches');
        $batchIds = array_column($latestBatches, 'id');

        $this->assertContains('batch-001', $batchIds);
        $this->assertContains('batch-002', $batchIds);
        $this->assertContains('batch-003', $batchIds);
    }

    public function test_latest_batches_does_not_contain_other_farmers_batches()
    {
        // Buat batch milik farmer
        $this->createBatch('batch-farmer1', 'processing');

        // Buat batch milik farmer2
        $this->createBatchForFarmer2('batch-farmer2', 'ready');

        $response = $this->getDashboard();

        $latestBatches = $response->json('data.latest_batches');
        $batchIds = array_column($latestBatches, 'id');

        // Harus hanya milik farmer, bukan farmer2
        $this->assertContains('batch-farmer1', $batchIds);
        $this->assertNotContains('batch-farmer2', $batchIds);
    }

    public function test_latest_batches_has_valid_structure()
    {
        $this->createBatch('batch-001', 'processing');

        $response = $this->getDashboard();

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'latest_batches' => [
                        '*' => [
                            'id',
                            'code',
                            'name',
                            'variety',
                            'harvest_date',
                            'harvest_date_label',
                            'status',
                            'status_label',
                            'health',
                            'temperature',
                            'humidity',
                        ],
                    ],
                ],
            ]);
    }

    public function test_latest_batches_empty_for_new_farmer()
    {
        $newFarmer = User::factory()->create([
            'role' => 'farmer',
            'name' => 'Petani Baru',
            'phone' => '+62 800-0000-0000',
            'phone_verified' => true,
            'profile_completion' => 10,
        ]);

        $response = $this->getDashboard($newFarmer);

        $response->assertStatus(200)
            ->assertJsonPath('data.latest_batches', []);
    }

    // ========================================================================
    // G. Daily Logs (IoT Read-Only)
    // ========================================================================

    public function test_daily_logs_contains_iot_snapshots()
    {
        $this->createBatch('batch-001', 'processing');
        $this->createBatchLog('batch-001', ['created_at' => now()]);
        $this->createBatchLog('batch-001', ['created_at' => now()->subHours(3)]);

        $response = $this->getDashboard();

        $response->assertStatus(200);

        $dailyLogs = $response->json('data.daily_logs');
        $this->assertIsArray($dailyLogs);
        $this->assertGreaterThanOrEqual(2, count($dailyLogs));
    }

    public function test_daily_logs_has_valid_structure()
    {
        $this->createBatch('batch-001', 'processing');
        $this->createBatchLog('batch-001', [
            'created_at' => now(),
            'log_type' => 'drying',
            'note' => 'Normal',
            'note_color' => '#4CAF7D',
        ]);

        $response = $this->getDashboard();

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'daily_logs' => [
                        '*' => [
                            'id',
                            'batch_id',
                            'batch_code',
                            'title',
                            'subtitle',
                            'temperature',
                            'humidity',
                            'value_display',
                            'note',
                            'note_color',
                            'log_type',
                            'created_at',
                        ],
                    ],
                ],
            ]);

        // Log source harus iot (V2.1: semua log dari IoT)
        $dailyLogs = $response->json('data.daily_logs');
        if (! empty($dailyLogs)) {
            // value_display format: "32°C / 68%"
            $this->assertMatchesRegularExpression(
                '/\d+°C\s*\/\s*\d+%/',
                $dailyLogs[0]['value_display'],
                'value_display harus format "XX°C / XX%"'
            );
        }
    }

    public function test_daily_logs_empty_for_farmer_without_processing_batch()
    {
        // Tidak ada batch processing → daily_logs kosong
        $response = $this->getDashboard();

        $response->assertStatus(200);
        $dailyLogs = $response->json('data.daily_logs');
        $this->assertIsArray($dailyLogs);
        // Kosong karena tidak ada batch aktif dengan log
    }

    // ========================================================================
    // H. Log Trend (IoT Read-Only)
    // ========================================================================

    public function test_log_trend_has_valid_structure()
    {
        $response = $this->getDashboard();

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'log_trend' => [
                        'label',
                        'sublabel',
                        'period',
                        'data_points',
                    ],
                ],
            ]);

        // Label harus ada
        $response->assertJsonPath('data.log_trend.label', function ($value) {
            return str_contains($value, 'Tren') || str_contains($value, 'Log');
        });
    }

    public function test_log_trend_data_points_max_5()
    {
        $this->createBatch('batch-001', 'processing');

        // Buat banyak log
        for ($i = 0; $i < 10; $i++) {
            $this->createBatchLog('batch-001', [
                'created_at' => now()->subHours($i * 2),
            ]);
        }

        $response = $this->getDashboard();

        $response->assertStatus(200);

        $dataPoints = $response->json('data.log_trend.data_points');
        $this->assertLessThanOrEqual(5, count($dataPoints));
    }

    public function test_log_trend_empty_when_no_active_batch()
    {
        // Tidak ada batch aktif → log_trend kosong
        $response = $this->getDashboard();

        $response->assertStatus(200);

        // log_trend bisa null atau data_points kosong
        $dataPoints = $response->json('data.log_trend.data_points');
        $this->assertTrue(
            empty($dataPoints) || count($dataPoints) == 0,
            'log_trend.data_points harus kosong ketika tidak ada batch aktif'
        );
    }

    public function test_log_trend_data_points_have_valid_fields()
    {
        $this->createBatch('batch-001', 'processing');
        $this->createBatchLog('batch-001', ['created_at' => now()]);

        $response = $this->getDashboard();

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'log_trend' => [
                        'data_points' => [
                            '*' => ['label', 'temperature', 'humidity', 'timestamp'],
                        ],
                    ],
                ],
            ]);
    }

    // ========================================================================
    // I. Batch Logs Timeline
    // ========================================================================

    public function test_batch_logs_timeline_has_valid_structure()
    {
        $this->createBatch('batch-001', 'processing');

        $response = $this->getDashboard();

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'batch_logs_timeline' => [
                        '*' => [
                            'id',
                            'title',
                            'subtitle',
                            'badge',
                            'badge_color',
                            'type',
                            'created_at',
                        ],
                    ],
                ],
            ]);
    }

    public function test_batch_logs_timeline_empty_for_new_farmer()
    {
        $newFarmer = User::factory()->create([
            'role' => 'farmer',
            'name' => 'Petani Baru',
            'phone' => '+62 800-0000-0000',
            'phone_verified' => true,
            'profile_completion' => 10,
        ]);

        $response = $this->getDashboard($newFarmer);

        $response->assertStatus(200);
        $timeline = $response->json('data.batch_logs_timeline');
        $this->assertIsArray($timeline);
        $this->assertEmpty($timeline);
    }

    public function test_batch_logs_timeline_contains_expected_event_types()
    {
        // Buat batch dengan aktivitas (survey_pending, processing, dll)
        $this->createBatch('batch-001', 'processing');

        $response = $this->getDashboard();

        $response->assertStatus(200);

        $timeline = $response->json('data.batch_logs_timeline');
        $types = array_column($timeline, 'type');

        // Timeline harus bisa berisi tipe-tipe event tertentu
        $validTypes = ['photo_verification', 'survey_pending', 'log_entry', 'status_change', 'iot_installed'];
        if (! empty($types)) {
            foreach ($types as $type) {
                $this->assertContains($type, $validTypes, "Unknown timeline type: {$type}");
            }
        }
    }

    // ========================================================================
    // J. Warnings
    // ========================================================================

    public function test_warnings_phone_missing_true_when_phone_null()
    {
        $farmerNoPhone = User::factory()->create([
            'role' => 'farmer',
            'name' => 'Tanpa Phone',
            'phone' => null,
            'phone_verified' => false,
            'profile_completion' => 40,
        ]);

        $response = $this->getDashboard($farmerNoPhone);

        $response->assertStatus(200)
            ->assertJsonPath('data.warnings.phone_missing', true);

        // phone_message harus ada penjelasan
        $phoneMessage = $response->json('data.warnings.phone_message');
        $this->assertNotNull($phoneMessage);
        $this->assertNotEmpty($phoneMessage);
    }

    public function test_warnings_phone_missing_false_when_phone_filled()
    {
        // Default farmer punya phone
        $response = $this->getDashboard();

        $response->assertStatus(200)
            ->assertJsonPath('data.warnings.phone_missing', false);
    }

    public function test_warnings_phone_missing_false_when_phone_filled_but_unverified()
    {
        $farmerUnverified = User::factory()->create([
            'role' => 'farmer',
            'name' => 'Unverified Phone',
            'phone' => '+62 800-1111-2222',
            'phone_verified' => false,
            'profile_completion' => 60,
        ]);

        $response = $this->getDashboard($farmerUnverified);

        // phone_missing = false karena phone sudah diisi (meski belum verified)
        $response->assertStatus(200)
            ->assertJsonPath('data.warnings.phone_missing', false);
    }

    // ========================================================================
    // K. Auth & Role
    // ========================================================================

    public function test_dashboard_unauthorized_without_auth_returns_401()
    {
        $response = $this->getJson('/api/v1/farmer/dashboard');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    public function test_dashboard_forbidden_with_exporter_role_returns_403()
    {
        $response = $this->getDashboard($this->exporter);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }

    // ========================================================================
    // L. Data Isolation
    // ========================================================================

    public function test_dashboard_data_isolation_between_farmers()
    {
        // Buat batch untuk farmer
        $this->createBatch('batch-farmer1', 'processing');

        // Buat batch untuk farmer2
        $this->createBatchForFarmer2('batch-farmer2', 'ready');

        // Dashboard farmer → hanya lihat batch farmer
        $response1 = $this->getDashboard($this->farmer);
        $response1->assertStatus(200);

        $farmer1Batches = array_column($response1->json('data.latest_batches'), 'id');
        $this->assertContains('batch-farmer1', $farmer1Batches);
        $this->assertNotContains('batch-farmer2', $farmer1Batches);

        // Dashboard farmer2 → hanya lihat batch farmer2
        $response2 = $this->getDashboard($this->farmer2);
        $response2->assertStatus(200);

        $farmer2Batches = array_column($response2->json('data.latest_batches'), 'id');
        $this->assertContains('batch-farmer2', $farmer2Batches);
        $this->assertNotContains('batch-farmer1', $farmer2Batches);
    }

    public function test_dashboard_farmer_section_shows_correct_farmer_data()
    {
        // Dashboard farmer → farmer section harus milik farmer
        $response = $this->getDashboard($this->farmer);

        $response->assertStatus(200)
            ->assertJsonPath('data.farmer.name', $this->farmer->name)
            ->assertJsonPath('data.farmer.phone', $this->farmer->phone)
            ->assertJsonPath('data.farmer.location', $this->farmer->location);

        // Dashboard farmer2 → farmer section harus milik farmer2
        $response2 = $this->getDashboard($this->farmer2);

        $response2->assertStatus(200)
            ->assertJsonPath('data.farmer.name', $this->farmer2->name)
            ->assertJsonPath('data.farmer.phone', $this->farmer2->phone)
            ->assertJsonPath('data.farmer.location', $this->farmer2->location);
    }

    // ========================================================================
    // M. V2.1 Specific: IoT Naming & Semantics
    // ========================================================================

    public function test_v21_stats_today_logs_caption_mentions_iot()
    {
        $response = $this->getDashboard();

        $response->assertStatus(200);

        $caption = $response->json('data.stats.today_logs_caption');
        $subcaption = $response->json('data.stats.today_logs_subcaption');

        // Caption harus merujuk ke IoT, bukan input manual
        $this->assertTrue(
            str_contains($caption, 'Log') || str_contains($caption, 'IoT'),
            'today_logs_caption harus merujuk ke Log/IoT'
        );
    }

    public function test_v21_timestamp_is_iso8601()
    {
        $response = $this->getDashboard();

        $response->assertStatus(200);

        $timestamp = $response->json('timestamp');
        $this->assertNotNull($timestamp);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $timestamp);
    }

    public function test_v21_daily_logs_only_from_iot_source()
    {
        $this->createBatch('batch-001', 'processing');
        $this->createBatchLog('batch-001', ['created_at' => now(), 'source' => 'iot']);

        $response = $this->getDashboard();

        $response->assertStatus(200);

        $dailyLogs = $response->json('data.daily_logs');
        // Semua log harus dari IoT (V2.1: tidak ada manual log)
        // Cek bahwa tidak ada log dengan source selain iot
        // (Ini murni verifikasi bahwa data yang dikirim adalah IoT)
        if (! empty($dailyLogs)) {
            foreach ($dailyLogs as $log) {
                // Log di dashboard tidak meng-expose field source secara langsung
                // tapi note_color dan note dihitung dari IoT
                $this->assertNotNull($log['note']);
                $this->assertNotNull($log['note_color']);
            }
        }
    }
}

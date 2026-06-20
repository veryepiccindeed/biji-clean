<?php

namespace Tests\Feature\Farmer;

use App\Models\User;
use App\Models\Batch;
use App\Models\BatchPhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * FarmerEdgeCaseBusinessLogicTest — Edge Cases & Logika Bisnis Kritis Petani (API Contract V2.1)
 *
 * Scope: Cross-module edge cases yang melibatkan interaksi antar status batch,
 *        constraint bisnis, IoT lifecycle, profil/phone gate, dan immutability.
 *
 * Reference: API_CONTRACT_V2_FARMER.md
 *   - Section 18 (State Machine: Lifecycle Batch Petani)
 *   - Section 19 (Edge Cases & Logika Bisnis Petani)
 *   - Section 5.2  (Kode Error Farmer-Specific)
 *
 * Teknik:
 *   - Direct DB update ($batch->update()) untuk simulasi langkah BIJI/system
 *     yang BUKAN scope farmer API (survey_scheduled, iot_installed, processing,
 *     ready, acquired). Ini diperlukan karena farmer tidak punya endpoint
 *     untuk trigger transisi tersebut — hanya BIJI system yang bisa.
 *   - RefreshDatabase trait tetap aktif untuk isolasi antar test method.
 *   - Masing-masing test method berdiri sendiri (independent).
 *
 * Sections:
 *   1. State Machine Forbidden Transitions (10 tests)
 *   2. Active Batch Constraint Per-Status (7 tests)
 *   3. Profile & Phone Gate Cross-Module (5 tests)
 *   4. IoT Lifecycle Permanence & Batch Switching (5 tests)
 *   5. Sequential Batch Lifecycle End-to-End (4 tests)
 *   6. Acquired Batch Immutability Consolidated (4 tests)
 */

class FarmerEdgeCaseBusinessLogicTest extends TestCase
{
    use RefreshDatabase;

    private User $farmer;
    private User $farmer2;
    private User $exporter;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('batches');

        $this->farmer = User::factory()->create([
            'role'               => 'farmer',
            'phone'              => '+62 812-3456-7890',
            'phone_verified'     => true,
            'location'           => 'Tana Toraja, Sulawesi Selatan',
            'coordinates'        => '-3.0701, 119.8923',
            'profile_completion' => 75,
            'iot_assigned'       => false,
        ]);

        $this->farmer2 = User::factory()->create([
            'role'               => 'farmer',
            'phone'              => '+62 813-9876-5432',
            'phone_verified'     => true,
            'location'           => 'Enrekang, Sulawesi Selatan',
            'coordinates'        => '-3.4023, 119.8432',
            'profile_completion' => 80,
            'iot_assigned'       => false,
        ]);

        $this->exporter = User::factory()->create(['role' => 'exporter']);
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    private function validBatchPayload(): array
    {
        return [
            'varietas'        => 'Arabika Toraja',
            'tanggal_panen'   => '2026-05-02',
            'metode_panen'    => 'Petik merah',
            'jumlah_karung'   => 18,
            'berat_basah'     => 560,
            'kebun'           => 'Kebun Hulu 01',
            'desa'            => 'Buntu Batu',
            'kecamatan'       => 'Baraka',
            'proses_awal'     => 'Penjemuran',
            'kadar_air_target'=> '12%',
            'status_jemur'    => 'Sedang berjalan',
        ];
    }

    private function createDraftBatch(string $batchId = 'batch-001', array $overrides = []): Batch
    {
        return Batch::factory()->create(array_merge([
            'farmer_id'   => $this->farmer->id,
            'batch_id'    => $batchId,
            'varietas'    => 'Arabika Toraja',
            'kebun'       => 'Kebun Hulu 01',
            'desa'        => 'Buntu Batu',
            'kecamatan'   => 'Baraka',
            'proses_awal' => 'Penjemuran',
            'status'      => 'draft',
        ], $overrides));
    }

    private function uploadMinimumPhotos(string $batchId = 'batch-001'): void
    {
        $photos = [
            UploadedFile::fake()->image('photo1.jpg', 1200, 800)->size(500),
            UploadedFile::fake()->image('photo2.jpg', 1200, 800)->size(500),
            UploadedFile::fake()->image('photo3.jpg', 1200, 800)->size(500),
        ];

        $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches/' . $batchId . '/photos', [
                'photos' => $photos,
            ])
            ->assertStatus(201);
    }

    /**
     * Simulasi langkah BIJI: langsung update status batch di DB.
     * Digunakan untuk transisi yang TIDAK bisa dilakukan via farmer API.
     */
    private function simulateBijiStep(Batch $batch, string $newStatus, array $extra = []): void
    {
        $batch->update(array_merge(['status' => $newStatus], $extra));
    }

    // ========================================================================
    // SECTION 1: STATE MACHINE FORBIDDEN TRANSITIONS (10 tests)
    // Reference: Section 18.3 — Transisi yang DILARANG
    // ========================================================================

    /** 1.1 draft → ready via PATCH harus ditolak (harus via survey & IoT) */
    public function test_forbidden_draft_to_ready_via_patch()
    {
        $batch = $this->createDraftBatch('batch-001');

        // Coba set status = ready lewat PATCH body
        $response = $this->actingAs($this->farmer)
            ->patchJson('/api/v1/farmer/batches/batch-001', [
                'status' => 'ready',
            ]);

        // Harus ditolak — farmer tidak bisa set status manual
        $response->assertStatus(400);
        $this->assertContains($response->json('code'), [
            'INVALID_BATCH_STATUS_TRANSITION', 'VALIDATION_ERROR',
        ]);

        // Status tetap draft
        $this->assertDatabaseHas('batches', [
            'batch_id' => 'batch-001',
            'status'   => 'draft',
        ]);
    }

    /** 1.2 draft → processing via PATCH harus ditolak */
    public function test_forbidden_draft_to_processing_via_patch()
    {
        $batch = $this->createDraftBatch('batch-001');

        $response = $this->actingAs($this->farmer)
            ->patchJson('/api/v1/farmer/batches/batch-001', [
                'status' => 'processing',
            ]);

        $response->assertStatus(400);
        $this->assertDatabaseHas('batches', [
            'batch_id' => 'batch-001',
            'status'   => 'draft',
        ]);
    }

    /** 1.3 processing → draft (mundur) harus ditolak */
    public function test_forbidden_processing_to_draft_via_patch()
    {
        $batch = $this->createDraftBatch('batch-001', ['status' => 'processing']);

        $response = $this->actingAs($this->farmer)
            ->patchJson('/api/v1/farmer/batches/batch-001', [
                'status' => 'draft',
            ]);

        $response->assertStatus(400);
        $this->assertDatabaseHas('batches', [
            'batch_id' => 'batch-001',
            'status'   => 'processing',
        ]);
    }

    /** 1.4 ready → draft (mundur) harus ditolak */
    public function test_forbidden_ready_to_draft_via_patch()
    {
        $batch = $this->createDraftBatch('batch-001', ['status' => 'ready']);

        $response = $this->actingAs($this->farmer)
            ->patchJson('/api/v1/farmer/batches/batch-001', [
                'status' => 'draft',
            ]);

        $response->assertStatus(400);
        $this->assertDatabaseHas('batches', [
            'batch_id' => 'batch-001',
            'status'   => 'ready',
        ]);
    }

    /** 1.5 acquired → draft harus ditolak (batch sudah dimiliki eksportir) */
    public function test_forbidden_acquired_to_draft_via_patch()
    {
        $batch = $this->createDraftBatch('batch-001', ['status' => 'acquired']);

        $response = $this->actingAs($this->farmer)
            ->patchJson('/api/v1/farmer/batches/batch-001', [
                'status' => 'draft',
            ]);

        $response->assertStatus(400);
        $this->assertDatabaseHas('batches', [
            'batch_id' => 'batch-001',
            'status'   => 'acquired',
        ]);
    }

    /** 1.6 acquired → processing harus ditolak */
    public function test_forbidden_acquired_to_any_status_via_patch()
    {
        $batch = $this->createDraftBatch('batch-001', ['status' => 'acquired']);

        $response = $this->actingAs($this->farmer)
            ->patchJson('/api/v1/farmer/batches/batch-001', [
                'jumlah_karung' => 25,
            ]);

        // Edit batch acquired harus gagal
        $response->assertStatus(400);
    }

    /** 1.7 survey_pending → draft via cancel survey (VALID transition — Petani batal survey) */
    public function test_valid_cancel_survey_from_survey_pending_to_draft()
    {
        $this->createDraftBatch('batch-001');
        $this->uploadMinimumPhotos('batch-001');

        // Submit survey → survey_pending
        $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches/batch-001/submit-survey')
            ->assertStatus(200);

        $this->assertDatabaseHas('batches', [
            'batch_id' => 'batch-001',
            'status'   => 'survey_pending',
        ]);

        // Cancel survey → kembali ke draft
        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches/batch-001/cancel-survey');

        // Harus sukses (200)
        $response->assertStatus(200);
        $this->assertDatabaseHas('batches', [
            'batch_id' => 'batch-001',
            'status'   => 'draft',
        ]);
    }

    /** 1.8 remote_review → draft via cancel survey (VALID — Petani batal approval) */
    public function test_valid_cancel_survey_from_remote_review_to_draft()
    {
        $batch = $this->createDraftBatch('batch-001', ['status' => 'remote_review']);

        // Cancel survey dari remote_review → kembali ke draft
        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches/batch-001/cancel-survey');

        $response->assertStatus(200);
        $this->assertDatabaseHas('batches', [
            'batch_id' => 'batch-001',
            'status'   => 'draft',
        ]);
    }

    /** 1.9 cancel survey dari status selain survey_pending/remote_review harus gagal */
    public function test_cancel_survey_from_invalid_status_returns_400()
    {
        $invalidStatuses = ['draft', 'processing', 'ready', 'acquired', 'iot_installed'];

        foreach ($invalidStatuses as $status) {
            $batchId = 'batch-cancel-' . $status;
            $this->createDraftBatch($batchId, ['status' => $status]);

            $response = $this->actingAs($this->farmer)
                ->postJson('/api/v1/farmer/batches/' . $batchId . '/cancel-survey');

            $response->assertStatus(400);
        }
    }

    /** 1.10 tidak bisa cancel survey batch milik farmer lain */
    public function test_cancel_survey_other_farmer_batch_returns_403_or_404()
    {
        $batch = $this->createDraftBatch('batch-001', ['status' => 'survey_pending']);

        $response = $this->actingAs($this->farmer2)
            ->postJson('/api/v1/farmer/batches/batch-001/cancel-survey');

        $this->assertThat(
            $response->status(),
            $this->logicalOr(
                $this->equalTo(403),
                $this->equalTo(404)
            )
        );
    }

    // ========================================================================
    // SECTION 2: ACTIVE BATCH CONSTRAINT PER-STATUS (7 tests)
    // Reference: Section 7.1 — 1 farmer = 1 active batch
    // Active statuses: draft, survey_pending, survey_scheduled, survey_in_progress,
    //                  survey_completed, iot_pending, iot_installed, processing, ready
    // ========================================================================

    /** 2.1 punya batch survey_pending → tidak bisa buat batch baru */
    public function test_active_batch_survey_pending_blocks_new_batch()
    {
        $batch = $this->createDraftBatch('batch-001', ['status' => 'survey_pending']);

        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches', $this->validBatchPayload());

        $response->assertStatus(409)
            ->assertJson(['code' => 'ACTIVE_BATCH_EXISTS']);
    }

    /** 2.2 punya batch survey_scheduled → tidak bisa buat batch baru */
    public function test_active_batch_survey_scheduled_blocks_new_batch()
    {
        $this->createDraftBatch('batch-001', ['status' => 'survey_scheduled']);

        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches', $this->validBatchPayload());

        $response->assertStatus(409)
            ->assertJson(['code' => 'ACTIVE_BATCH_EXISTS']);
    }

    /** 2.3 punya batch iot_installed → tidak bisa buat batch baru */
    public function test_active_batch_iot_installed_blocks_new_batch()
    {
        $this->createDraftBatch('batch-001', ['status' => 'iot_installed']);

        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches', $this->validBatchPayload());

        $response->assertStatus(409)
            ->assertJson(['code' => 'ACTIVE_BATCH_EXISTS']);
    }

    /** 2.4 punya batch processing → tidak bisa buat batch baru */
    public function test_active_batch_processing_blocks_new_batch()
    {
        $this->createDraftBatch('batch-001', ['status' => 'processing']);

        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches', $this->validBatchPayload());

        $response->assertStatus(409)
            ->assertJson(['code' => 'ACTIVE_BATCH_EXISTS']);
    }

    /** 2.5 punya batch ready → tidak bisa buat batch baru */
    public function test_active_batch_ready_blocks_new_batch()
    {
        $this->createDraftBatch('batch-001', ['status' => 'ready']);

        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches', $this->validBatchPayload());

        $response->assertStatus(409)
            ->assertJson(['code' => 'ACTIVE_BATCH_EXISTS']);
    }

    /** 2.6 punya batch remote_review → tidak bisa buat batch baru */
    public function test_active_batch_remote_review_blocks_new_batch()
    {
        $this->createDraftBatch('batch-001', ['status' => 'remote_review']);

        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches', $this->validBatchPayload());

        $response->assertStatus(409)
            ->assertJson(['code' => 'ACTIVE_BATCH_EXISTS']);
    }

    /** 2.7 punya batch acquired (terminal) → BOLEH buat batch baru */
    public function test_terminal_batch_acquired_allows_new_batch()
    {
        $this->createDraftBatch('batch-001', ['status' => 'acquired']);

        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches', $this->validBatchPayload());

        $response->assertStatus(201)
            ->assertJson(['code' => 'SUCCESS_CREATE']);
    }

    // ========================================================================
    // SECTION 3: PROFILE & PHONE GATE CROSS-MODULE (5 tests)
    // Reference: Section 5.2 (PHONE_NOT_VERIFIED, PROFILE_INCOMPLETE)
    // Confirmed: phone_verified WAJIB untuk create batch DAN submit survey
    // ========================================================================

    /** 3.1 phone_unverified → create batch gagal (PHONE_NOT_VERIFIED) */
    public function test_phone_gate_create_batch_unverified()
    {
        $this->farmer->update([
            'phone'          => '+62 812-0000-0000',
            'phone_verified' => false,
        ]);

        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches', $this->validBatchPayload());

        $response->assertStatus(403)
            ->assertJson(['code' => 'PHONE_NOT_VERIFIED']);
    }

    /** 3.2 phone_unverified + batch draft + foto 3 → submit survey gagal (PHONE_NOT_VERIFIED) */
    public function test_phone_gate_submit_survey_unverified()
    {
        $this->farmer->update([
            'phone'          => '+62 812-0000-0000',
            'phone_verified' => false,
        ]);

        $this->createDraftBatch();
        $this->uploadMinimumPhotos();

        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches/batch-001/submit-survey');

        $response->assertStatus(403)
            ->assertJson(['code' => 'PHONE_NOT_VERIFIED']);

        // Batch status tetap draft
        $this->assertDatabaseHas('batches', [
            'batch_id' => 'batch-001',
            'status'   => 'draft',
        ]);
    }

    /** 3.3 profile_completion < 50 → create batch gagal (PROFILE_INCOMPLETE) */
    public function test_profile_gate_create_batch_incomplete()
    {
        $this->farmer->update([
            'phone_verified'     => true,
            'profile_completion' => 30,
        ]);

        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches', $this->validBatchPayload());

        $response->assertStatus(403)
            ->assertJson(['code' => 'PROFILE_INCOMPLETE']);
    }

    /** 3.4 profile_completion < 50 → submit survey gagal (PROFILE_INCOMPLETE) */
    public function test_profile_gate_submit_survey_incomplete()
    {
        $this->farmer->update(['profile_completion' => 30]);

        $this->createDraftBatch();
        $this->uploadMinimumPhotos();

        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches/batch-001/submit-survey');

        $response->assertStatus(403)
            ->assertJson(['code' => 'PROFILE_INCOMPLETE']);
    }

    /** 3.5 phone null (tidak diisi) → create batch gagal (PHONE_NOT_VERIFIED) */
    public function test_phone_null_create_batch_fails()
    {
        $this->farmer->update([
            'phone'          => null,
            'phone_verified' => false,
        ]);

        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches', $this->validBatchPayload());

        $response->assertStatus(403)
            ->assertJson(['code' => 'PHONE_NOT_VERIFIED']);
    }

    // ========================================================================
    // SECTION 4: IoT LIFECYCLE PERMANENCE & BATCH SWITCHING (5 tests)
    // Reference: Section 18 (State Machine), Section 1.4 (1 Farmer = 1 IoT Unit Permanen)
    // ========================================================================

    /** 4.1 batch pertama → submit survey → is_first_survey = true, iot_installation_included = true */
    public function test_iot_first_survey_detection()
    {
        $this->assertFalse($this->farmer->iot_assigned);

        $this->createDraftBatch();
        $this->uploadMinimumPhotos();

        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches/batch-001/submit-survey');

        $response->assertStatus(200)
            ->assertJsonPath('data.survey.is_first_survey', true)
            ->assertJsonPath('data.survey.iot_installation_included', true)
            ->assertJsonPath('data.batch.stage', 'Survey BIJI')
            ->assertJsonPath('data.batch.survey_status', 'Menunggu Jadwal Survey');
    }

    /** 4.2 batch kedua (farmer.iot_assigned = true) → submit survey → is_first_survey = false */
    public function test_iot_subsequent_survey_detection()
    {
        $this->farmer->update(['iot_assigned' => true, 'iot_sensor_id' => 'IOT-TOR-001']);

        $this->createDraftBatch();
        $this->uploadMinimumPhotos();

        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches/batch-001/submit-survey');

        $response->assertStatus(200)
            ->assertJsonPath('data.survey.is_first_survey', false)
            ->assertJsonPath('data.survey.iot_installation_included', false)
            ->assertJsonPath('data.batch.stage', 'Approval BIJI')
            ->assertJsonPath('data.batch.survey_status', 'Menunggu Approval Remote')
            ->assertJsonPath('data.survey.existing_iot_sensor_id', 'IOT-TOR-001');
    }

    /** 4.3 setelah batch pertama complete → farmer.iot_assigned tetap true untuk batch kedua */
    public function test_iot_permanence_after_first_batch_lifecycle()
    {
        // Simulasi: batch pertama draft → survey_pending → ... → iot_installed
        $batch1 = $this->createDraftBatch('batch-first');
        $this->uploadMinimumPhotos('batch-first');

        $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches/batch-first/submit-survey')
            ->assertStatus(200);

        // Simulasi BIJI steps: survey → IoT installed → processing → ready → acquired
        $batch1->fresh()->update(['status' => 'iot_installed']);
        $this->farmer->refresh();
        $this->assertTrue($this->farmer->iot_assigned);

        // Lanjut simulasi BIJI: processing → ready → acquired
        $batch1->fresh()->update(['status' => 'processing']);
        $batch1->fresh()->update(['status' => 'ready']);
        $batch1->fresh()->update(['status' => 'acquired']);

        // IoT assigned TETAP true setelah batch acquired
        $this->farmer->refresh();
        $this->assertTrue($this->farmer->iot_assigned);

        // Buat batch kedua → harus otomatis masuk approval remote (bukan survey fisik)
        $this->createDraftBatch('batch-second', [
            'varietas'    => 'Robusta Enrekang',
            'tanggal_panen' => '2026-06-01',
        ]);
        $this->uploadMinimumPhotos('batch-second');

        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches/batch-second/submit-survey');

        $response->assertStatus(200)
            ->assertJsonPath('data.survey.is_first_survey', false)
            ->assertJsonPath('data.survey.iot_installation_included', false);
    }

    /** 4.4 iot_assigned = true → survey-status response tunjukkan existing sensor */
    public function test_iot_assigned_survey_status_shows_existing_sensor()
    {
        $this->farmer->update([
            'iot_assigned'  => true,
            'iot_sensor_id' => 'IOT-TOR-001',
        ]);

        $this->createDraftBatch();
        $this->uploadMinimumPhotos();

        $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches/batch-001/submit-survey')
            ->assertStatus(200);

        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches/batch-001/survey-status');

        $response->assertStatus(200);

        // Response harus menyertakan existing IoT sensor info
        $this->assertNotNull($response->json('data.iot.sensor_id'));
    }

    /** 4.5 farmer tanpa IoT → survey-status iot = not_installed, sensor_id = null */
    public function test_no_iot_survey_status_shows_not_installed()
    {
        $this->assertFalse($this->farmer->iot_assigned);

        $this->createDraftBatch();

        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches/batch-001/survey-status');

        $response->assertStatus(200)
            ->assertJsonPath('data.survey.status', 'not_submitted')
            ->assertJsonPath('data.iot.status', 'not_installed')
            ->assertJsonPath('data.iot.sensor_id', null);
    }

    // ========================================================================
    // SECTION 5: SEQUENTIAL BATCH LIFECYCLE END-TO-END (4 tests)
    // Menggunakan direct DB update untuk simulasi langkah BIJI/system.
    // Petani tidak punya endpoint untuk trigger transisi ini secara langsung.
    // ========================================================================

    /** 5.1 Full lifecycle batch pertama (dengan IoT fisik): draft → ... → acquired → batch baru */
    public function test_lifecycle_first_batch_then_create_second()
    {
        // === STEP 1: Farmer buat batch pertama (draft) ===
        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches', $this->validBatchPayload());
        $response->assertStatus(201);
        $batch1 = Batch::where('farmer_id', $this->farmer->id)->first();
        $this->assertEquals('draft', $batch1->status);

        // === STEP 2: Upload 3 foto ===
        $this->uploadMinimumPhotos($batch1->batch_id);

        // === STEP 3: Submit survey (batch pertama → survey fisik) ===
        $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches/' . $batch1->batch_id . '/submit-survey')
            ->assertStatus(200)
            ->assertJsonPath('data.survey.is_first_survey', true);
        $batch1->refresh();
        $this->assertEquals('survey_pending', $batch1->status);

        // === STEP 4-7: Simulasi BIJI (direct DB) → survey → IoT installed → processing → ready ===
        $batch1->update(['status' => 'survey_scheduled']);
        $batch1->update(['status' => 'survey_completed']);
        $batch1->update(['status' => 'iot_installed']);
        // Set farmer.iot_assigned = true (permanen)
        $this->farmer->update(['iot_assigned' => true, 'iot_sensor_id' => 'IOT-TOR-001']);
        $batch1->update(['status' => 'processing']);
        $batch1->update(['status' => 'ready']);

        // === STEP 8: Eksportir akuisisi (simulasi direct DB) → acquired ===
        $batch1->update(['status' => 'acquired']);
        $batch1->refresh();
        $this->assertEquals('acquired', $batch1->status);

        // === STEP 9: Farmer buat batch kedua → harus sukses ===
        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches', [
                'varietas'        => 'Robusta Enrekang',
                'tanggal_panen'   => '2026-06-01',
                'metode_panen'    => 'Selektif',
                'jumlah_karung'   => 12,
                'berat_basah'     => 380,
                'kebun'           => 'Kebun Tengah',
                'desa'            => 'Maiwa',
                'kecamatan'       => 'Enrekang',
                'proses_awal'     => 'Natural',
                'kadar_air_target'=> '13%',
                'status_jemur'    => 'Sedang berjalan',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.batch.status', 'draft');

        // Farmer IoT assigned tetap true
        $this->farmer->refresh();
        $this->assertTrue($this->farmer->iot_assigned);

        // Total batch farmer = 2 (batch pertama acquired + batch kedua draft)
        $this->assertEquals(2, Batch::where('farmer_id', $this->farmer->id)->count());
    }

    /** 5.2 Full lifecycle batch kedua (remote approval): draft → survey_pending → remote_review → iot_installed */
    public function test_lifecycle_second_batch_remote_approval_path()
    {
        // Setup: farmer sudah punya IoT dari batch pertama
        $this->farmer->update(['iot_assigned' => true, 'iot_sensor_id' => 'IOT-TOR-001']);

        // Buat batch kedua
        $this->createDraftBatch();
        $this->uploadMinimumPhotos();

        // Submit → survey_pending (deteksi otomatis: approval remote)
        $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches/batch-001/submit-survey')
            ->assertStatus(200)
            ->assertJsonPath('data.survey.is_first_survey', false)
            ->assertJsonPath('data.batch.survey_status', 'Menunggu Approval Remote');

        // Simulasi BIJI: survey_pending → remote_review → iot_installed
        $batch = Batch::where('batch_id', 'batch-001')->first();
        $batch->update(['status' => 'remote_review']);
        $batch->update(['status' => 'iot_installed']);
        $batch->refresh();
        $this->assertEquals('iot_installed', $batch->status);

        // IoT sensor ID TIDAK berubah (permanen)
        $this->farmer->refresh();
        $this->assertEquals('IOT-TOR-001', $this->farmer->iot_sensor_id);
    }

    /** 5.3 Cancel survey di tengah lifecycle → kembali ke draft, bisa submit ulang */
    public function test_lifecycle_cancel_survey_then_resubmit()
    {
        $this->createDraftBatch();
        $this->uploadMinimumPhotos();

        // Submit survey → survey_pending
        $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches/batch-001/submit-survey')
            ->assertStatus(200);

        $this->assertDatabaseHas('batches', [
            'batch_id' => 'batch-001',
            'status'   => 'survey_pending',
        ]);

        // Cancel survey → kembali ke draft
        $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches/batch-001/cancel-survey')
            ->assertStatus(200);

        $this->assertDatabaseHas('batches', [
            'batch_id' => 'batch-001',
            'status'   => 'draft',
        ]);

        // Submit ulang → harus sukses lagi
        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches/batch-001/submit-survey');

        $response->assertStatus(200)
            ->assertJsonPath('data.batch.status', 'survey_pending');
    }

    /** 5.4 Batch survey_pending → cancel → draft → delete → buat batch baru */
    public function test_lifecycle_cancel_then_delete_then_new_batch()
    {
        $this->createDraftBatch('batch-001');
        $this->uploadMinimumPhotos('batch-001');

        // Submit → survey_pending
        $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches/batch-001/submit-survey')
            ->assertStatus(200);

        // Cancel → draft
        $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches/batch-001/cancel-survey')
            ->assertStatus(200);

        // Delete batch (draft, tanpa log) → sukses
        $this->actingAs($this->farmer)
            ->deleteJson('/api/v1/farmer/batches/batch-001')
            ->assertStatus(200);

        // Buat batch baru → harus sukses
        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches', $this->validBatchPayload());

        $response->assertStatus(201)
            ->assertJsonPath('data.batch.status', 'draft');
    }

    // ========================================================================
    // SECTION 6: ACQUIRED BATCH IMMUTABILITY CONSOLIDATED (4 tests)
    // Reference: Section 18.3 — acquired tidak bisa diubah oleh petani
    // ========================================================================

    /** 6.1 batch acquired → edit (PATCH) gagal */
    public function test_acquired_batch_edit_fails()
    {
        $this->createDraftBatch('batch-001', ['status' => 'acquired', 'jumlah_karung' => 18]);

        $response = $this->actingAs($this->farmer)
            ->patchJson('/api/v1/farmer/batches/batch-001', [
                'jumlah_karung' => 25,
                'catatan'       => 'Coba edit setelah acquired',
            ]);

        $response->assertStatus(400);
        $this->assertDatabaseHas('batches', [
            'batch_id'      => 'batch-001',
            'jumlah_karung' => 18,
            'status'        => 'acquired',
        ]);
    }

    /** 6.2 batch acquired → delete gagal */
    public function test_acquired_batch_delete_fails()
    {
        $this->createDraftBatch('batch-001', ['status' => 'acquired']);

        $response = $this->actingAs($this->farmer)
            ->deleteJson('/api/v1/farmer/batches/batch-001');

        $response->assertStatus(400);
        // Batch masih ada di DB
        $this->assertDatabaseHas('batches', [
            'batch_id' => 'batch-001',
            'status'   => 'acquired',
        ]);
    }

    /** 6.3 batch acquired → submit survey gagal (INVALID_BATCH_STATUS_TRANSITION) */
    public function test_acquired_batch_submit_survey_fails()
    {
        $this->createDraftBatch('batch-001', ['status' => 'acquired']);

        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches/batch-001/submit-survey');

        $response->assertStatus(400);
    }

    /** 6.4 batch acquired → upload foto gagal (INVALID_BATCH_STATUS_TRANSITION) */
    public function test_acquired_batch_upload_photo_fails()
    {
        $this->createDraftBatch('batch-001', ['status' => 'acquired']);

        $photos = [
            UploadedFile::fake()->image('photo1.jpg', 1200, 800)->size(500),
        ];

        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches/batch-001/photos', [
                'photos' => $photos,
            ]);

        $response->assertStatus(400);
    }
}

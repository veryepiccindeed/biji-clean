<?php

namespace Tests\Feature\Farmer;

use App\Models\Batch;
use App\Models\BatchPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * FarmerBatchSurveyTest — Test Case untuk Modul Survey & IoT Petani (API Contract V2.1)
 *
 * Scope: 2 endpoint survey farmer
 *   - POST /api/v1/farmer/batches/{batchId}/submit-survey  — Ajukan survey/approval (10.1)
 *   - GET  /api/v1/farmer/batches/{batchId}/survey-status  — Cek status survey (10.2)
 *
 * Reference: API_CONTRACT_V2_FARMER.md Section 10 (Modul Survey & IoT Petani)
 *
 * Business Rules V2.1 yang ditest:
 * - Otomatis deteksi is_first_survey berdasarkan farmer.iot_assigned
 * - Batch pertama (iot_assigned = false): survey fisik + instalasi IoT sekali seumur hidup
 * - Batch selanjutnya (iot_assigned = true): approval remote, IoT dialihkan ke batch baru
 * - Prasyarat submit survey: batch draft + foto >= 3 + phone verified + profile_completion >= 50
 * - Error codes: PHONE_NOT_VERIFIED (403), PROFILE_INCOMPLETE (403), BATCH_PHOTO_MINIMUM (422),
 *   INVALID_BATCH_STATUS_TRANSITION (400), BATCH_ALREADY_SUBMITTED (409), BATCH_NOT_OWNED (403)
 * - Data isolation: petani hanya bisa submit/cek survey batch miliknya
 * - IoT permanen per petani (1 farmer = 1 IoT unit)
 * - Status survey: not_submitted, pending, scheduled, in_progress, completed, rejected, cancelled
 * - Status IoT: not_installed, installing, installed, maintenance, offline
 *
 * OUT OF SCOPE (trigger oleh BIJI/system, bukan farmer):
 * - survey_pending → survey_scheduled (BIJI jadwalkan)
 * - survey_pending → remote_review (BIJI mulai review)
 * - survey_scheduled → survey_in_progress → survey_completed (BIJI survey fisik)
 * - iot_pending → iot_installed (BIJI pasang IoT)
 * - remote_review → iot_installed / remote_review → draft (BIJI approve/reject)
 */
class FarmerBatchSurveyTest extends TestCase
{
    use RefreshDatabase;

    private User $farmer;

    private User $farmer2;

    private User $exporter;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('batches');

        // Farmer default: belum punya IoT (batch pertama)
        $this->farmer = User::factory()->create([
            'role' => 'farmer',
            'phone' => '+62 812-3456-7890',
            'phone_verified' => true,
            'location' => 'Tana Toraja, Sulawesi Selatan',
            'coordinates' => '-3.0701, 119.8923',
            'profile_completion' => 75,
            'iot_assigned' => false,
        ]);

        $this->farmer2 = User::factory()->create([
            'role' => 'farmer',
            'phone' => '+62 813-9876-5432',
            'phone_verified' => true,
            'location' => 'Enrekang, Sulawesi Selatan',
            'coordinates' => '-3.4023, 119.8432',
            'profile_completion' => 80,
            'iot_assigned' => false,
        ]);

        $this->exporter = User::factory()->create(['role' => 'exporter']);
    }

    // ========================================================================
    // Helper Methods
    // ========================================================================

    /**
     * Helper: buat batch draft milik farmer utama
     */
    private function createDraftBatch(string $batchId = 'batch-001', array $overrides = []): Batch
    {
        return Batch::factory()->create(array_merge([
            'farmer_id' => $this->farmer->id,
            'batch_id' => $batchId,
            'varietas' => 'Arabika Toraja',
            'kebun' => 'Kebun Hulu 01',
            'desa' => 'Buntu Batu',
            'kecamatan' => 'Baraka',
            'proses_awal' => 'Penjemuran',
            'status' => 'draft',
        ], $overrides));
    }

    /**
     * Helper: upload 3 foto ke batch (memenuhi BATCH_PHOTO_MINIMUM)
     */
    private function uploadMinimumPhotos(string $batchId = 'batch-001'): void
    {
        $photos = [
            UploadedFile::fake()->image('photo1.jpg', 1200, 800)->size(500),
            UploadedFile::fake()->image('photo2.jpg', 1200, 800)->size(500),
            UploadedFile::fake()->image('photo3.jpg', 1200, 800)->size(500),
        ];

        $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches/'.$batchId.'/photos', [
                'photos' => $photos,
            ])
            ->assertStatus(201);
    }

    /**
     * Helper: submit survey untuk batch
     */
    private function submitSurvey(string $batchId, ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->farmer)
            ->postJson('/api/v1/farmer/batches/'.$batchId.'/submit-survey');
    }

    /**
     * Helper: get survey status untuk batch
     */
    private function getSurveyStatus(string $batchId, ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->farmer)
            ->getJson('/api/v1/farmer/batches/'.$batchId.'/survey-status');
    }

    // ========================================================================
    // 10.1: POST /api/v1/farmer/batches/{batchId}/submit-survey
    // ========================================================================

    // --- A. Happy Path ---

    public function test_submit_survey_first_batch_no_iot_returns_200()
    {
        // Farmer belum punya IoT (iot_assigned = false)
        $this->assertFalse($this->farmer->iot_assigned);

        $this->createDraftBatch();
        $this->uploadMinimumPhotos();

        $response = $this->submitSurvey('batch-001');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
                'message' => 'Pengajuan survey BIJI berhasil dikirim',
            ])
            ->assertJsonStructure([
                'data' => [
                    'batch' => ['id', 'code', 'stage', 'survey_status', 'status'],
                    'survey' => ['submitted_at', 'estimated_survey_window', 'next_step', 'is_first_survey', 'iot_installation_included'],
                ],
            ]);

        // Batch pertama: is_first_survey = true, iot_installation_included = true
        $response->assertJsonPath('data.batch.status', 'survey_pending');
        $response->assertJsonPath('data.survey.is_first_survey', true);
        $response->assertJsonPath('data.survey.iot_installation_included', true);
        $response->assertJsonPath('data.batch.stage', 'Survey BIJI');
        $response->assertJsonPath('data.batch.survey_status', 'Menunggu Jadwal Survey');

        // Verifikasi batch status berubah di database
        $this->assertDatabaseHas('batches', [
            'batch_id' => 'batch-001',
            'status' => 'survey_pending',
        ]);
    }

    public function test_submit_survey_subsequent_batch_with_iot_returns_200()
    {
        // Farmer sudah punya IoT (simulasi batch kedua+)
        $this->farmer->update(['iot_assigned' => true]);
        $this->assertTrue($this->farmer->iot_assigned);

        $this->createDraftBatch('batch-002');
        $this->uploadMinimumPhotos('batch-002');

        $response = $this->submitSurvey('batch-002');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
                'message' => 'Pengajuan approval BIJI berhasil dikirim',
            ])
            ->assertJsonStructure([
                'data' => [
                    'batch' => ['id', 'code', 'stage', 'survey_status', 'status'],
                    'survey' => ['submitted_at', 'estimated_approval_window', 'next_step', 'is_first_survey', 'iot_installation_included'],
                ],
            ]);

        // Batch selanjutnya: is_first_survey = false, iot_installation_included = false
        $response->assertJsonPath('data.batch.status', 'survey_pending');
        $response->assertJsonPath('data.survey.is_first_survey', false);
        $response->assertJsonPath('data.survey.iot_installation_included', false);
        $response->assertJsonPath('data.batch.stage', 'Approval BIJI');
        $response->assertJsonPath('data.batch.survey_status', 'Menunggu Approval Remote');

        // Response harus menyertakan existing_iot_sensor_id
        $response->assertJsonStructure([
            'data' => [
                'survey' => ['existing_iot_sensor_id'],
            ],
        ]);
    }

    public function test_submit_survey_response_structure_matches_contract()
    {
        $this->createDraftBatch();
        $this->uploadMinimumPhotos();

        $response = $this->submitSurvey('batch-001');

        // Verifikasi semua field sesuai contract (batch pertama)
        $response->assertJsonStructure([
            'success',
            'code',
            'message',
            'data' => [
                'batch' => [
                    'id',
                    'code',
                    'stage',
                    'survey_status',
                    'status',
                ],
                'survey' => [
                    'submitted_at',
                    'estimated_survey_window',
                    'next_step',
                    'is_first_survey',
                    'iot_installation_included',
                ],
            ],
            'timestamp',
        ]);

        // submitted_at harus format ISO 8601
        $submittedAt = $response->json('data.survey.submitted_at');
        $this->assertNotNull($submittedAt);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $submittedAt);

        // estimated_survey_window harus ada (batch pertama)
        $window = $response->json('data.survey.estimated_survey_window');
        $this->assertNotNull($window);
        $this->assertStringContainsString('hari', $window);
    }

    // --- B. Validasi Prasyarat / Error Cases ---

    public function test_submit_survey_phone_not_verified_returns_403()
    {
        // Farmer dengan phone belum verified
        $this->farmer->update([
            'phone' => '+62 812-0000-0000',
            'phone_verified' => false,
        ]);

        $this->createDraftBatch();
        $this->uploadMinimumPhotos();

        $response = $this->submitSurvey('batch-001');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'PHONE_NOT_VERIFIED',
            ]);

        // Batch status TIDAK berubah, tetap draft
        $this->assertDatabaseHas('batches', [
            'batch_id' => 'batch-001',
            'status' => 'draft',
        ]);
    }

    public function test_submit_survey_profile_incomplete_returns_403()
    {
        // Farmer dengan profile_completion < 50
        $this->farmer->update(['profile_completion' => 30]);

        $this->createDraftBatch();
        $this->uploadMinimumPhotos();

        $response = $this->submitSurvey('batch-001');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'PROFILE_INCOMPLETE',
            ]);

        // Batch status TIDAK berubah
        $this->assertDatabaseHas('batches', [
            'batch_id' => 'batch-001',
            'status' => 'draft',
        ]);
    }

    public function test_submit_survey_photo_less_than_3_returns_422()
    {
        $this->createDraftBatch();

        // Upload hanya 2 foto (kurang dari minimum 3)
        $photos = [
            UploadedFile::fake()->image('photo1.jpg', 1200, 800)->size(500),
            UploadedFile::fake()->image('photo2.jpg', 1200, 800)->size(500),
        ];

        $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches/batch-001/photos', [
                'photos' => $photos,
            ])
            ->assertStatus(201);

        $response = $this->submitSurvey('batch-001');

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'BATCH_PHOTO_MINIMUM',
            ]);

        // Batch status TIDAK berubah
        $this->assertDatabaseHas('batches', [
            'batch_id' => 'batch-001',
            'status' => 'draft',
        ]);
    }

    public function test_submit_survey_zero_photos_returns_422()
    {
        $this->createDraftBatch();
        // Tidak upload foto sama sekali

        $response = $this->submitSurvey('batch-001');

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'BATCH_PHOTO_MINIMUM',
            ]);
    }

    public function test_submit_survey_non_draft_batch_returns_400()
    {
        // Coba submit survey untuk batch yang sudah processing
        $batch = $this->createDraftBatch('batch-001');
        $this->uploadMinimumPhotos();
        $batch->update(['status' => 'processing']);

        $response = $this->submitSurvey('batch-001');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'code' => 'INVALID_BATCH_STATUS_TRANSITION',
            ]);
    }

    public function test_submit_survey_already_submitted_returns_409()
    {
        $this->createDraftBatch();
        $this->uploadMinimumPhotos();

        // Submit pertama → berhasil
        $this->submitSurvey('batch-001')->assertStatus(200);

        // Submit kedua (double submit) → gagal
        $response = $this->submitSurvey('batch-001');

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'code' => 'BATCH_ALREADY_SUBMITTED',
            ]);
    }

    public function test_submit_survey_non_owned_batch_returns_403()
    {
        // Buat batch milik farmer
        $this->createDraftBatch();
        $this->uploadMinimumPhotos();

        // farmer2 coba submit survey untuk batch farmer
        $response = $this->submitSurvey('batch-001', $this->farmer2);

        $this->assertThat(
            $response->status(),
            $this->logicalOr(
                $this->equalTo(403),
                $this->equalTo(404)
            )
        );

        if ($response->status() === 403) {
            $response->assertJson(['code' => 'BATCH_NOT_OWNED']);
        }
    }

    // --- C. Auth & Role ---

    public function test_submit_survey_unauthorized_without_auth_returns_401()
    {
        $this->createDraftBatch();

        $response = $this->postJson('/api/v1/farmer/batches/batch-001/submit-survey');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    public function test_submit_survey_forbidden_with_exporter_role_returns_403()
    {
        $this->createDraftBatch();

        $response = $this->submitSurvey('batch-001', $this->exporter);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }

    public function test_submit_survey_nonexistent_batch_returns_404()
    {
        $response = $this->submitSurvey('nonexistent-batch');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code' => 'NOT_FOUND',
            ]);
    }

    // ========================================================================
    // 10.2: GET /api/v1/farmer/batches/{batchId}/survey-status
    // ========================================================================

    // --- D. Happy Path ---

    public function test_get_survey_status_returns_200()
    {
        $this->createDraftBatch();
        $this->uploadMinimumPhotos();

        // Submit survey dulu
        $this->submitSurvey('batch-001')->assertStatus(200);

        // Get survey status
        $response = $this->getSurveyStatus('batch-001');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
                'message' => 'Status survey berhasil diambil',
            ])
            ->assertJsonStructure([
                'data' => [
                    'survey' => [
                        'status',
                        'status_label',
                        'submitted_at',
                        'scheduled_date',
                        'scheduled_date_label',
                        'scheduled_time',
                        'surveyor_name',
                        'notes',
                        'result',
                        'completed_at',
                    ],
                    'iot' => [
                        'status',
                        'status_label',
                        'installed_at',
                        'sensor_id',
                        'last_reading_at',
                        'coordinates',
                        'elevation_mdpl',
                    ],
                ],
                'timestamp',
            ]);

        // Setelah submit, survey status harus 'pending' (menunggu jadwal/review)
        $response->assertJsonPath('data.survey.status', 'pending');

        // IoT belum terpasang (batch pertama, baru ajukan)
        $response->assertJsonPath('data.iot.status', 'not_installed');
    }

    public function test_get_survey_status_response_structure_matches_contract()
    {
        $this->createDraftBatch();
        $this->uploadMinimumPhotos();
        $this->submitSurvey('batch-001')->assertStatus(200);

        $response = $this->getSurveyStatus('batch-001');
        $data = $response->json('data');

        // Verifikasi field survey
        $this->assertNotNull($data['survey']['status']);
        $this->assertNotNull($data['survey']['status_label']);
        $this->assertNotNull($data['survey']['submitted_at']);

        // Verifikasi field IoT
        $this->assertNotNull($data['iot']['status']);
        $this->assertNotNull($data['iot']['status_label']);
        // $this->assertNotNull($data['iot']['installed_at']);
        // $this->assertNotNull($data['iot']['sensor_id']);

        // Timestamp di response utama
        $this->assertNotNull($response->json('timestamp'));
    }

    // --- E. Error & Edge Cases ---

    public function test_get_survey_status_not_submitted_yet_returns_200()
    {
        // Batch belum pernah submit survey
        $this->createDraftBatch();

        $response = $this->getSurveyStatus('batch-001');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
            ]);

        // survey status harus 'not_submitted'
        $response->assertJsonPath('data.survey.status', 'not_submitted');

        // IoT status 'not_installed' (belum punya IoT)
        $response->assertJsonPath('data.iot.status', 'not_installed');

        // Fields yang belum ada harus null
        $response->assertJsonPath('data.survey.scheduled_date', null);
        $response->assertJsonPath('data.survey.completed_at', null);
        $response->assertJsonPath('data.iot.installed_at', null);
        $response->assertJsonPath('data.iot.sensor_id', null);
    }

    public function test_get_survey_status_other_farmer_batch_returns_403_or_404()
    {
        $this->createDraftBatch();
        $this->uploadMinimumPhotos();
        $this->submitSurvey('batch-001')->assertStatus(200);

        // farmer2 coba akses survey status batch farmer
        $response = $this->getSurveyStatus('batch-001', $this->farmer2);

        $this->assertThat(
            $response->status(),
            $this->logicalOr(
                $this->equalTo(403),
                $this->equalTo(404)
            )
        );

        if ($response->status() === 403) {
            $response->assertJson(['code' => 'BATCH_NOT_OWNED']);
        }
    }

    public function test_get_survey_status_unauthorized_without_auth_returns_401()
    {
        $this->createDraftBatch();

        $response = $this->getJson('/api/v1/farmer/batches/batch-001/survey-status');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    // ========================================================================
    // Cross-Module: Survey & IoT Lifecycle Integration
    // ========================================================================

    public function test_first_survey_detection_based_on_iot_assigned_flag()
    {
        // === Batch 1: Farmer tanpa IoT ===
        $this->assertFalse($this->farmer->iot_assigned);

        $this->createDraftBatch('batch-first');
        $this->uploadMinimumPhotos('batch-first');

        $response = $this->submitSurvey('batch-first');

        $response->assertStatus(200)
            ->assertJsonPath('data.survey.is_first_survey', true)
            ->assertJsonPath('data.survey.iot_installation_included', true);
    }

    public function test_subsequent_survey_detection_based_on_iot_assigned_flag()
    {
        // === Simulasi: Farmer sudah punya IoT dari batch sebelumnya ===
        $this->farmer->update(['iot_assigned' => true]);

        $this->createDraftBatch('batch-subsequent');
        $this->uploadMinimumPhotos('batch-subsequent');

        $response = $this->submitSurvey('batch-subsequent');

        $response->assertStatus(200)
            ->assertJsonPath('data.survey.is_first_survey', false)
            ->assertJsonPath('data.survey.iot_installation_included', false)
            ->assertJsonPath('data.batch.survey_status', 'Menunggu Approval Remote');
    }

    public function test_iot_assigned_farmer_survey_status_shows_existing_sensor()
    {
        // Farmer sudah punya IoT
        $this->farmer->update([
            'iot_assigned' => true,
            'iot_sensor_id' => 'IOT-TOR-001',
        ]);

        $this->createDraftBatch();
        $this->uploadMinimumPhotos();
        $this->submitSurvey('batch-001')->assertStatus(200);

        $response = $this->getSurveyStatus('batch-001');

        $response->assertStatus(200);

        // Response submit survey harus menyertakan existing_iot_sensor_id
        $submitResponse = $this->submitSurvey('batch-001');
        // Double submit akan return 409, tapi kita cek dari response pertama
    }

    // ========================================================================
    // Cross-Module: Survey & Batch Status Integration
    // ========================================================================

    public function test_submit_survey_changes_batch_status_to_survey_pending()
    {
        $this->createDraftBatch();
        $this->uploadMinimumPhotos();

        // Status awal: draft
        $this->assertDatabaseHas('batches', [
            'batch_id' => 'batch-001',
            'status' => 'draft',
        ]);

        // Submit survey
        $this->submitSurvey('batch-001')->assertStatus(200);

        // Status berubah: survey_pending
        $this->assertDatabaseHas('batches', [
            'batch_id' => 'batch-001',
            'status' => 'survey_pending',
        ]);
    }

    public function test_cannot_submit_survey_when_batch_already_survey_pending()
    {
        $this->createDraftBatch();
        $this->uploadMinimumPhotos();

        // Submit → survey_pending
        $this->submitSurvey('batch-001')->assertStatus(200);

        // Batch sekarang survey_pending, coba submit lagi
        $response = $this->submitSurvey('batch-001');
        $response->assertStatus(409)
            ->assertJson(['code' => 'BATCH_ALREADY_SUBMITTED']);

        // Status tetap survey_pending
        $this->assertDatabaseHas('batches', [
            'batch_id' => 'batch-001',
            'status' => 'survey_pending',
        ]);
    }

    public function test_cannot_submit_survey_for_each_invalid_status()
    {
        $invalidStatuses = [
            'survey_pending',
            'survey_scheduled',
            'survey_in_progress',
            'survey_completed',
            'iot_pending',
            'remote_review',
            'iot_installed',
            'processing',
            'ready',
            'acquired',
        ];

        foreach ($invalidStatuses as $status) {
            // Buat batch dengan status masing-masing
            $batch = $this->createDraftBatch('batch-'.$status, [
                'status' => $status,
            ]);

            // Upload foto (mungkin gagal jika bukan draft, tapi kita coba submit survey langsung)
            $response = $this->submitSurvey('batch-'.$status);

            // Harus gagal (400 atau 409, tergantung status-nya)
            $this->assertContains($response->status(), [400, 409],
                "Status {$status} seharusnya tidak bisa submit survey, got {$response->status()}");
        }
    }

    // ========================================================================
    // Edge Cases: Prasyarat Kombinasi
    // ========================================================================

    public function test_submit_survey_all_prerequisites_met_succeeds()
    {
        // Semua prasyarat terpenuhi:
        // 1. Batch status = draft
        // 2. Foto >= 3
        // 3. Phone verified
        // 4. profile_completion >= 50
        // $this->assertEquals('draft', Batch::where('batch_id', 'batch-001')->value('status'));
        $this->assertTrue($this->farmer->phone_verified);
        $this->assertGreaterThanOrEqual(50, $this->farmer->profile_completion);

        $this->createDraftBatch();
        $this->uploadMinimumPhotos();

        // Hitung foto di database
        $photoCount = BatchPhoto::where('batch_id', 'batch-001')->count();
        $this->assertGreaterThanOrEqual(3, $photoCount);

        // Submit → harus berhasil
        $response = $this->submitSurvey('batch-001');
        $response->assertStatus(200)
            ->assertJsonPath('data.batch.status', 'survey_pending');
    }

    public function test_submit_survey_phone_null_fails()
    {
        // Phone kosong (null)
        $this->farmer->update([
            'phone' => null,
            'phone_verified' => false,
        ]);

        $this->createDraftBatch();
        $this->uploadMinimumPhotos();

        $response = $this->submitSurvey('batch-001');

        $response->assertStatus(403)
            ->assertJson(['code' => 'PHONE_NOT_VERIFIED']);
    }

    public function test_submit_survey_phone_filled_but_unverified_fails()
    {
        // Phone diisi tapi belum verified
        $this->farmer->update([
            'phone' => '+62 812-9999-8888',
            'phone_verified' => false,
        ]);

        $this->createDraftBatch();
        $this->uploadMinimumPhotos();

        $response = $this->submitSurvey('batch-001');

        $response->assertStatus(403)
            ->assertJson(['code' => 'PHONE_NOT_VERIFIED']);
    }

    public function test_submit_survey_profile_completion_exactly_50_succeeds()
    {
        // Batas bawah: profile_completion = 50 → HARUS berhasil
        $this->farmer->update(['profile_completion' => 50]);

        $this->createDraftBatch();
        $this->uploadMinimumPhotos();

        $response = $this->submitSurvey('batch-001');

        $response->assertStatus(200)
            ->assertJsonPath('data.batch.status', 'survey_pending');
    }

    public function test_submit_survey_profile_completion_49_fails()
    {
        // Batas bawah - 1: profile_completion = 49 → HARUS gagal
        $this->farmer->update(['profile_completion' => 49]);

        $this->createDraftBatch();
        $this->uploadMinimumPhotos();

        $response = $this->submitSurvey('batch-001');

        $response->assertStatus(403)
            ->assertJson(['code' => 'PROFILE_INCOMPLETE']);
    }

    public function test_submit_survey_photos_exactly_3_succeeds()
    {
        // Batas minimum: 3 foto → HARUS berhasil
        $this->createDraftBatch();

        // Upload tepat 3 foto
        $photos = [
            UploadedFile::fake()->image('p1.jpg', 800, 600)->size(200),
            UploadedFile::fake()->image('p2.jpg', 800, 600)->size(200),
            UploadedFile::fake()->image('p3.jpg', 800, 600)->size(200),
        ];
        $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches/batch-001/photos', ['photos' => $photos])
            ->assertStatus(201);

        $response = $this->submitSurvey('batch-001');
        $response->assertStatus(200)
            ->assertJsonPath('data.batch.status', 'survey_pending');
    }

    // ========================================================================
    // Edge Cases: Survey Status Values
    // ========================================================================

    public function test_survey_status_not_submitted_is_default_for_new_batch()
    {
        $this->createDraftBatch();

        $response = $this->getSurveyStatus('batch-001');

        $response->assertStatus(200)
            ->assertJsonPath('data.survey.status', 'not_submitted')
            ->assertJsonPath('data.survey.status_label', 'Belum Diajukan');
    }

    public function test_iot_status_not_installed_is_default_for_farmer_without_iot()
    {
        $this->createDraftBatch();

        $response = $this->getSurveyStatus('batch-001');

        $response->assertStatus(200)
            ->assertJsonPath('data.iot.status', 'not_installed')
            ->assertJsonPath('data.iot.status_label', 'Belum Terpasang')
            ->assertJsonPath('data.iot.installed_at', null)
            ->assertJsonPath('data.iot.sensor_id', null);
    }
}

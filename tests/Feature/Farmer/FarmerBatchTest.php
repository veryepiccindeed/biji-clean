<?php

namespace Tests\Feature\Farmer;

use App\Models\User;
use App\Models\Batch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FarmerBatchTest — Test Case untuk Modul Manajemen Batch Petani (API Contract V2.1)
 *
 * Scope: 5 endpoint batch farmer (POST create, GET list, GET detail, PATCH update, DELETE)
 * Reference: API_CONTRACT_V2_FARMER.md Section 7 (Modul Manajemen Batch Petani)
 *
 * Business Rules V2.1 yang ditest:
 * - 1 farmer = 1 active batch (ACTIVE_BATCH_EXISTS)
 * - Hanya batch draft yang bisa di-edit/hapus
 * - Data isolation: petani hanya akses batch miliknya
 * - Auto-generate batch code (format: BJI-{VAR4CHAR}-{YYMMDD})
 * - Profil lengkap diperlukan untuk buat batch
 * - Validasi field: varietas, tanggal_panen, metode_panen, jumlah_karung, berat_basah, dll
 */

class FarmerBatchTest extends TestCase
{
    use RefreshDatabase;

    private User $farmer;
    private User $farmer2;
    private User $exporter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->farmer  = User::factory()->create([
            'role'              => 'farmer',
            'phone'             => '+62 812-3456-7890',
            'phone_verified'    => true,
            'location'          => 'Tana Toraja, Sulawesi Selatan',
            'coordinates'       => '-3.0701, 119.8923',
            'profile_completion'=> 75,
        ]);
        $this->farmer2 = User::factory()->create([
            'role'              => 'farmer',
            'phone'             => '+62 813-9876-5432',
            'phone_verified'    => true,
            'location'          => 'Enrekang, Sulawesi Selatan',
            'coordinates'       => '-3.4023, 119.8432',
            'profile_completion'=> 80,
        ]);
        $this->exporter = User::factory()->create(['role' => 'exporter']);
    }

    // ========================================================================
    // 7.1: POST /api/v1/farmer/batches — Buat Batch Baru
    // ========================================================================

    public function test_create_batch_returns_201_with_auto_generated_code()
    {
        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches', [
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
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'code'    => 'SUCCESS_CREATE',
                'message' => 'Batch berhasil dibuat sebagai draft',
            ])
            ->assertJsonStructure([
                'data' => [
                    'batch' => [
                        'id',
                        'code',
                        'name',
                        'varietas',
                        'tanggal_panen',
                        'tanggal_panen_label',
                        'metode_panen',
                        'jumlah_karung',
                        'berat_basah',
                        'kebun',
                        'desa',
                        'kecamatan',
                        'proses_awal',
                        'kadar_air_target',
                        'status_jemur',
                        'stage',
                        'survey_status',
                        'iot_status',
                        'photo_count',
                        'photo_minimum',
                        'log_count',
                        'status',
                        'completion_percent',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);

        // Verifikasi batch code auto-generated format: BJI-{4CHAR}-{YYMMDD}
        $batchCode = $response->json('data.batch.code');
        $this->assertMatchesRegularExpression('/^BJI-[A-Z]{4}-\d{6}$/', $batchCode);

        // Verifikasi status awal
        $response->assertJsonPath('data.batch.status', 'draft');
        $response->assertJsonPath('data.batch.stage', 'Draft Survey');
        $response->assertJsonPath('data.batch.survey_status', 'Menunggu Survey BIJI');
        $response->assertJsonPath('data.batch.iot_status', 'Belum Terpasang');
        $response->assertJsonPath('data.batch.photo_count', 0);
        $response->assertJsonPath('data.batch.photo_minimum', 3);
        $response->assertJsonPath('data.batch.log_count', 0);

        // Verifikasi batch tersimpan di database
        $this->assertDatabaseHas('batches', [
            'farmer_id'      => $this->farmer->id,
            'varietas'       => 'Arabika Toraja',
            'tanggal_panen'  => '2026-05-02',
            'metode_panen'   => 'Petik merah',
            'jumlah_karung'  => 18,
            'status'         => 'draft',
        ]);
    }

    public function test_create_batch_with_optional_catatan()
    {
        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches', [
                'varietas'        => 'Robusta Enrekang',
                'tanggal_panen'   => '2026-05-09',
                'metode_panen'    => 'Selektif',
                'jumlah_karung'   => 12,
                'berat_basah'     => 380,
                'kebun'           => 'Kebun Tengah 02',
                'desa'            => 'Maiwa',
                'kecamatan'       => 'Enrekang',
                'proses_awal'     => 'Honey',
                'kadar_air_target'=> '11%',
                'status_jemur'    => 'Selesai',
                'catatan'         => 'Cuaca cerah selama 3 hari terakhir, penjemuran stabil.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.batch.catatan', 'Cuaca cerah selama 3 hari terakhir, penjemuran stabil.')
            ->assertJsonPath('data.batch.varietas', 'Robusta Enrekang');
    }

    // --- Validasi Field ---

    public function test_create_batch_missing_required_fields_returns_422()
    {
        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches', [
                'varietas' => 'Arabika Toraja',
                // sisanya kosong
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code'    => 'VALIDATION_ERROR',
            ]);
    }

    public function test_create_batch_empty_body_returns_422()
    {
        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches', []);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code'    => 'VALIDATION_ERROR',
            ]);
    }

    public function test_create_batch_invalid_varietas_too_short_returns_422()
    {
        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches', [
                'varietas'        => 'A', // min 2 karakter
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
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code'    => 'VALIDATION_ERROR',
            ]);
    }

    public function test_create_batch_invalid_metode_panen_returns_422()
    {
        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches', [
                'varietas'        => 'Arabika Toraja',
                'tanggal_panen'   => '2026-05-02',
                'metode_panen'    => 'Metode Salah', // bukan enum yang valid
                'jumlah_karung'   => 18,
                'berat_basah'     => 560,
                'kebun'           => 'Kebun Hulu 01',
                'desa'            => 'Buntu Batu',
                'kecamatan'       => 'Baraka',
                'proses_awal'     => 'Penjemuran',
                'kadar_air_target'=> '12%',
                'status_jemur'    => 'Sedang berjalan',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code'    => 'VALIDATION_ERROR',
            ]);
    }

    public function test_create_batch_invalid_proses_awal_returns_422()
    {
        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches', [
                'varietas'        => 'Arabika Toraja',
                'tanggal_panen'   => '2026-05-02',
                'metode_panen'    => 'Petik merah',
                'jumlah_karung'   => 18,
                'berat_basah'     => 560,
                'kebun'           => 'Kebun Hulu 01',
                'desa'            => 'Buntu Batu',
                'kecamatan'       => 'Baraka',
                'proses_awal'     => 'Proses Salah', // bukan enum valid
                'kadar_air_target'=> '12%',
                'status_jemur'    => 'Sedang berjalan',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code'    => 'VALIDATION_ERROR',
            ]);
    }

    public function test_create_batch_invalid_kadar_air_target_returns_422()
    {
        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches', [
                'varietas'        => 'Arabika Toraja',
                'tanggal_panen'   => '2026-05-02',
                'metode_panen'    => 'Petik merah',
                'jumlah_karung'   => 18,
                'berat_basah'     => 560,
                'kebun'           => 'Kebun Hulu 01',
                'desa'            => 'Buntu Batu',
                'kecamatan'       => 'Baraka',
                'proses_awal'     => 'Penjemuran',
                'kadar_air_target'=> '15%', // bukan 11%, 12%, atau 13%
                'status_jemur'    => 'Sedang berjalan',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code'    => 'VALIDATION_ERROR',
            ]);
    }

    public function test_create_batch_invalid_status_jemur_returns_422()
    {
        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches', [
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
                'status_jemur'    => 'Status Salah', // bukan enum valid
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code'    => 'VALIDATION_ERROR',
            ]);
    }

    public function test_create_batch_jumlah_karung_zero_returns_422()
    {
        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches', [
                'varietas'        => 'Arabika Toraja',
                'tanggal_panen'   => '2026-05-02',
                'metode_panen'    => 'Petik merah',
                'jumlah_karung'   => 0, // min 1
                'berat_basah'     => 560,
                'kebun'           => 'Kebun Hulu 01',
                'desa'            => 'Buntu Batu',
                'kecamatan'       => 'Baraka',
                'proses_awal'     => 'Penjemuran',
                'kadar_air_target'=> '12%',
                'status_jemur'    => 'Sedang berjalan',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code'    => 'VALIDATION_ERROR',
            ]);
    }

    public function test_create_batch_berat_basah_negative_returns_422()
    {
        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches', [
                'varietas'        => 'Arabika Toraja',
                'tanggal_panen'   => '2026-05-02',
                'metode_panen'    => 'Petik merah',
                'jumlah_karung'   => 18,
                'berat_basah'     => -10, // min 1
                'kebun'           => 'Kebun Hulu 01',
                'desa'            => 'Buntu Batu',
                'kecamatan'       => 'Baraka',
                'proses_awal'     => 'Penjemuran',
                'kadar_air_target'=> '12%',
                'status_jemur'    => 'Sedang berjalan',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code'    => 'VALIDATION_ERROR',
            ]);
    }

    public function test_create_batch_all_valid_enum_values_metode_panen()
    {
        $enumValues = ['Petik merah', 'Petik campur', 'Selektif'];

        foreach ($enumValues as $value) {
            $response = $this->actingAs($this->farmer)
                ->postJson('/api/v1/farmer/batches', [
                    'varietas'        => 'Arabika Toraja',
                    'tanggal_panen'   => '2026-05-02',
                    'metode_panen'    => $value,
                    'jumlah_karung'   => 18,
                    'berat_basah'     => 560,
                    'kebun'           => 'Kebun Hulu 01',
                    'desa'            => 'Buntu Batu',
                    'kecamatan'       => 'Baraka',
                    'proses_awal'     => 'Penjemuran',
                    'kadar_air_target'=> '12%',
                    'status_jemur'    => 'Sedang berjalan',
                ]);

            // Pertama akan sukses (201), sisanya ACTIVE_BATCH_EXISTS (409)
            $this->assertContains($response->status(), [201, 409]);
        }
    }

    public function test_create_batch_all_valid_enum_values_proses_awal()
    {
        $enumValues = ['Penjemuran', 'Fermentasi', 'Honey', 'Natural'];

        foreach ($enumValues as $value) {
            // Buat farmer baru per iterasi untuk menghindari ACTIVE_BATCH_EXISTS
            $freshFarmer = User::factory()->create([
                'role'               => 'farmer',
                'phone'              => '+62 812-' . rand(1000, 9999) . '-0000',
                'phone_verified'     => true,
                'location'           => 'Toraja',
                'coordinates'        => '-3.07, 119.89',
                'profile_completion' => 75,
            ]);

            $response = $this->actingAs($freshFarmer)
                ->postJson('/api/v1/farmer/batches', [
                    'varietas'        => 'Arabika Toraja',
                    'tanggal_panen'   => '2026-05-02',
                    'metode_panen'    => 'Petik merah',
                    'jumlah_karung'   => 18,
                    'berat_basah'     => 560,
                    'kebun'           => 'Kebun Hulu 01',
                    'desa'            => 'Buntu Batu',
                    'kecamatan'       => 'Baraka',
                    'proses_awal'     => $value,
                    'kadar_air_target'=> '12%',
                    'status_jemur'    => 'Sedang berjalan',
                ]);

            $response->assertStatus(201);
        }
    }

    // --- Business Rules ---

    public function test_create_batch_active_batch_exists_returns_409()
    {
        // Buat batch pertama (sukses)
        $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches', $this->validBatchPayload())
            ->assertStatus(201);

        // Coba buat batch kedua saat masih ada batch aktif
        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches', [
                'varietas'        => 'Robusta Enrekang',
                'tanggal_panen'   => '2026-05-15',
                'metode_panen'    => 'Selektif',
                'jumlah_karung'   => 10,
                'berat_basah'     => 300,
                'kebun'           => 'Kebun Tengah',
                'desa'            => 'Maiwa',
                'kecamatan'       => 'Enrekang',
                'proses_awal'     => 'Natural',
                'kadar_air_target'=> '13%',
                'status_jemur'    => 'Sedang berjalan',
            ]);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'code'    => 'ACTIVE_BATCH_EXISTS',
            ])
            ->assertJsonStructure([
                'details' => [
                    'active_batch_id',
                    'active_batch_code',
                    'active_batch_status',
                    'hint',
                ],
            ]);
    }

    public function test_create_batch_after_previous_acquired_returns_201()
    {
        // Buat batch pertama
        $batch = Batch::factory()->create([
            'farmer_id'      => $this->farmer->id,
            'varietas'       => 'Arabika Toraja',
            'tanggal_panen'  => '2026-04-01',
            'status'         => 'acquired', // terminal status — slot kosong
        ]);

        // Setelah batch sebelumnya acquired, bisa buat batch baru
        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches', $this->validBatchPayload());

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'code'    => 'SUCCESS_CREATE',
            ]);
    }

    public function test_create_batch_profile_incomplete_returns_403()
    {
        $incompleteFarmer = User::factory()->create([
            'role'               => 'farmer',
            'phone'              => null,
            'phone_verified'     => false,
            'location'           => null,
            'coordinates'        => null,
            'profile_completion' => 25, // < 50
        ]);

        $response = $this->actingAs($incompleteFarmer)
            ->postJson('/api/v1/farmer/batches', $this->validBatchPayload());

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code'    => 'PROFILE_INCOMPLETE',
            ]);
    }

    public function test_create_batch_duplicate_varietas_tanggal_kebun_returns_409()
    {
        // Buat batch pertama
        $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches', $this->validBatchPayload())
            ->assertStatus(201);

        // Set batch pertama jadi acquired supaya bisa buat batch baru
        Batch::where('farmer_id', $this->farmer->id)
            ->update(['status' => 'acquired']);

        // Coba buat batch dengan varietas + tanggal_panen + kebun SAMA
        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches', $this->validBatchPayload());

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'code'    => 'DRAFT_ALREADY_EXISTS',
            ]);
    }

    // --- Auth & Role ---

    public function test_create_batch_unauthorized_without_auth()
    {
        $response = $this->postJson('/api/v1/farmer/batches', $this->validBatchPayload());

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code'    => 'UNAUTHORIZED',
            ]);
    }

    public function test_create_batch_forbidden_with_exporter_role()
    {
        $response = $this->actingAs($this->exporter)
            ->postJson('/api/v1/farmer/batches', $this->validBatchPayload());

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code'    => 'FORBIDDEN',
            ]);
    }

    // ========================================================================
    // 7.2: GET /api/v1/farmer/batches — Daftar Batch Petani
    // ========================================================================

    public function test_list_batches_returns_200_with_pagination()
    {
        Batch::factory()->create([
            'farmer_id'     => $this->farmer->id,
            'varietas'      => 'Arabika Toraja',
            'status'        => 'draft',
            'tanggal_panen' => '2026-05-02',
        ]);

        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code'    => 'SUCCESS',
                'message' => 'Daftar batch petani berhasil diambil',
            ])
            ->assertJsonStructure([
                'success',
                'code',
                'message',
                'data'    => [],
                'pagination' => [
                    'cursor',
                    'hasMore',
                    'limit',
                    'total',
                ],
                'timestamp',
            ]);
    }

    public function test_list_batches_returns_empty_when_no_batches()
    {
        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches');

        $response->assertStatus(200)
            ->assertJsonPath('pagination.total', 0);
    }

    public function test_list_batches_only_returns_own_batches()
    {
        // Buat batch untuk farmer
        Batch::factory()->count(3)->create([
            'farmer_id' => $this->farmer->id,
            'status'    => 'draft',
        ]);

        // Buat batch untuk farmer2 (harus TIDAK terlihat oleh farmer)
        Batch::factory()->count(2)->create([
            'farmer_id' => $this->farmer2->id,
            'status'    => 'draft',
        ]);

        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches');

        $response->assertStatus(200)
            ->assertJsonPath('pagination.total', 3);
    }

    public function test_list_batches_with_status_filter_draft()
    {
        Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status'    => 'draft',
        ]);
        Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status'    => 'processing',
        ]);

        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches?status=draft');

        $response->assertStatus(200)
            ->assertJsonPath('pagination.total', 1);
    }

    public function test_list_batches_with_status_filter_processing()
    {
        Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status'    => 'draft',
        ]);
        Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status'    => 'processing',
        ]);

        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches?status=processing');

        $response->assertStatus(200)
            ->assertJsonPath('pagination.total', 1);
    }

    public function test_list_batches_with_status_filter_ready()
    {
        Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status'    => 'draft',
        ]);
        Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'status'    => 'ready',
        ]);

        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches?status=ready');

        $response->assertStatus(200)
            ->assertJsonPath('pagination.total', 1);
    }

    public function test_list_batches_with_custom_limit()
    {
        Batch::factory()->count(5)->create([
            'farmer_id' => $this->farmer->id,
            'status'    => 'draft',
        ]);

        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches?limit=3');

        $response->assertStatus(200)
            ->assertJsonPath('pagination.limit', 3)
            ->assertJsonPath('data', function ($data) {
                return count($data) <= 3;
            });
    }

    public function test_list_batches_limit_capped_at_100()
    {
        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches?limit=200');

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(100, $response->json('pagination.limit'));
    }

    public function test_list_batches_with_sort_by_tanggal_panen()
    {
        Batch::factory()->count(3)->create([
            'farmer_id' => $this->farmer->id,
            'status'    => 'draft',
        ]);

        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches?sort=tanggal_panen&sort_dir=asc');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code'    => 'SUCCESS',
            ]);
    }

    public function test_list_batches_with_cursor_pagination()
    {
        Batch::factory()->count(3)->create([
            'farmer_id' => $this->farmer->id,
            'status'    => 'draft',
        ]);

        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches?cursor=eyJpZCI6ImJhdGNoLTAwMiJ9');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code'    => 'SUCCESS',
            ]);
    }

    public function test_list_batches_unauthorized_without_auth()
    {
        $response = $this->getJson('/api/v1/farmer/batches');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code'    => 'UNAUTHORIZED',
            ]);
    }

    public function test_list_batches_forbidden_with_exporter_role()
    {
        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/farmer/batches');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code'    => 'FORBIDDEN',
            ]);
    }

    // ========================================================================
    // 7.3: GET /api/v1/farmer/batches/{batchId} — Detail Batch
    // ========================================================================

    public function test_show_batch_returns_detail()
    {
        $batch = Batch::factory()->create([
            'farmer_id'     => $this->farmer->id,
            'batch_id'      => 'batch-001',
            'varietas'      => 'Arabika Toraja',
            'tanggal_panen' => '2026-05-02',
            'kebun'         => 'Kebun Hulu 01',
            'desa'          => 'Buntu Batu',
            'kecamatan'     => 'Baraka',
            'proses_awal'   => 'Penjemuran',
            'status'        => 'draft',
        ]);

        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches/batch-001');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code'    => 'SUCCESS',
                'message' => 'Detail batch berhasil diambil',
            ])
            ->assertJsonStructure([
                'data' => [
                    'batch' => [
                        'id',
                        'code',
                        'name',
                        'identity',
                        'stage',
                        'survey_status',
                        'iot_status',
                        'status',
                        'photos',
                        'management',
                        'management_steps',
                        'actions_available',
                        'logs_timeline',
                        'health_status',
                        'temperature',
                        'humidity',
                        'iot_data',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    }

    public function test_show_batch_draft_has_correct_action_flags()
    {
        Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'batch_id'  => 'batch-001',
            'status'    => 'draft',
        ]);

        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches/batch-001');

        $response->assertStatus(200)
            ->assertJsonPath('data.batch.actions_available.can_edit', true)
            ->assertJsonPath('data.batch.actions_available.can_delete', true);
    }

    public function test_show_batch_not_found()
    {
        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches/invalid-batch-id');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code'    => 'NOT_FOUND',
            ]);
    }

    public function test_show_batch_data_isolation_other_farmer_returns_403_or_404()
    {
        // Buat batch milik farmer
        Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'batch_id'  => 'batch-001',
            'status'    => 'draft',
        ]);

        // farmer2 coba akses batch farmer
        $response = $this->actingAs($this->farmer2)
            ->getJson('/api/v1/farmer/batches/batch-001');

        // Harus 403 (BATCH_NOT_OWNED) atau 404 (NOT_FOUND)
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

    public function test_show_batch_unauthorized_without_auth()
    {
        $response = $this->getJson('/api/v1/farmer/batches/batch-001');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code'    => 'UNAUTHORIZED',
            ]);
    }

    public function test_show_batch_forbidden_with_exporter_role()
    {
        Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'batch_id'  => 'batch-001',
            'status'    => 'draft',
        ]);

        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/farmer/batches/batch-001');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code'    => 'FORBIDDEN',
            ]);
    }

    // ========================================================================
    // 7.4: PATCH /api/v1/farmer/batches/{batchId} — Update Batch Draft
    // ========================================================================

    public function test_update_batch_draft_returns_200()
    {
        Batch::factory()->create([
            'farmer_id'     => $this->farmer->id,
            'batch_id'      => 'batch-001',
            'varietas'      => 'Arabika Toraja',
            'jumlah_karung' => 18,
            'status'        => 'draft',
        ]);

        $response = $this->actingAs($this->farmer)
            ->patchJson('/api/v1/farmer/batches/batch-001', [
                'jumlah_karung' => 20,
                'berat_basah'   => 620,
                'catatan'       => 'Update: Penjemuran selesai, mulai proses honey.',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code'    => 'SUCCESS_UPDATE',
                'message' => 'Batch berhasil diperbarui',
            ])
            ->assertJsonPath('data.batch.jumlah_karung', 20)
            ->assertJsonPath('data.batch.berat_basah', 620)
            ->assertJsonPath('data.batch.catatan', 'Update: Penjemuran selesai, mulai proses honey.')
            ->assertJsonPath('data.batch.status', 'draft');
    }

    public function test_update_batch_partial_update_proses_awal_only()
    {
        Batch::factory()->create([
            'farmer_id'   => $this->farmer->id,
            'batch_id'    => 'batch-001',
            'proses_awal' => 'Penjemuran',
            'status'      => 'draft',
        ]);

        $response = $this->actingAs($this->farmer)
            ->patchJson('/api/v1/farmer/batches/batch-001', [
                'proses_awal' => 'Honey',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code'    => 'SUCCESS_UPDATE',
            ])
            ->assertJsonPath('data.batch.proses_awal', 'Honey');
    }

    public function test_update_batch_partial_update_status_jemur_only()
    {
        Batch::factory()->create([
            'farmer_id'    => $this->farmer->id,
            'batch_id'     => 'batch-001',
            'status_jemur' => 'Sedang berjalan',
            'status'       => 'draft',
        ]);

        $response = $this->actingAs($this->farmer)
            ->patchJson('/api/v1/farmer/batches/batch-001', [
                'status_jemur' => 'Selesai',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.batch.status_jemur', 'Selesai');
    }

    public function test_update_batch_non_draft_processing_returns_400()
    {
        Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'batch_id'  => 'batch-001',
            'status'    => 'processing',
        ]);

        $response = $this->actingAs($this->farmer)
            ->patchJson('/api/v1/farmer/batches/batch-001', [
                'jumlah_karung' => 25,
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'code'    => 'INVALID_BATCH_STATUS_TRANSITION',
            ]);
    }

    public function test_update_batch_non_draft_ready_returns_400()
    {
        Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'batch_id'  => 'batch-001',
            'status'    => 'ready',
        ]);

        $response = $this->actingAs($this->farmer)
            ->patchJson('/api/v1/farmer/batches/batch-001', [
                'jumlah_karung' => 25,
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'code'    => 'INVALID_BATCH_STATUS_TRANSITION',
            ]);
    }

    public function test_update_batch_non_draft_acquired_returns_400()
    {
        Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'batch_id'  => 'batch-001',
            'status'    => 'acquired',
        ]);

        $response = $this->actingAs($this->farmer)
            ->patchJson('/api/v1/farmer/batches/batch-001', [
                'jumlah_karung' => 25,
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'code'    => 'INVALID_BATCH_STATUS_TRANSITION',
            ]);
    }

    public function test_update_batch_not_found()
    {
        $response = $this->actingAs($this->farmer)
            ->patchJson('/api/v1/farmer/batches/invalid-batch-id', [
                'jumlah_karung' => 25,
            ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code'    => 'NOT_FOUND',
            ]);
    }

    public function test_update_batch_data_isolation_other_farmer_returns_403_or_404()
    {
        Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'batch_id'  => 'batch-001',
            'status'    => 'draft',
        ]);

        $response = $this->actingAs($this->farmer2)
            ->patchJson('/api/v1/farmer/batches/batch-001', [
                'jumlah_karung' => 25,
            ]);

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

    public function test_update_batch_validation_error_invalid_field()
    {
        Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'batch_id'  => 'batch-001',
            'status'    => 'draft',
        ]);

        $response = $this->actingAs($this->farmer)
            ->patchJson('/api/v1/farmer/batches/batch-001', [
                'metode_panen' => 'Metode Tidak Valid',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code'    => 'VALIDATION_ERROR',
            ]);
    }

    public function test_update_batch_unauthorized_without_auth()
    {
        $response = $this->patchJson('/api/v1/farmer/batches/batch-001', [
            'jumlah_karung' => 25,
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code'    => 'UNAUTHORIZED',
            ]);
    }

    public function test_update_batch_forbidden_with_exporter_role()
    {
        Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'batch_id'  => 'batch-001',
            'status'    => 'draft',
        ]);

        $response = $this->actingAs($this->exporter)
            ->patchJson('/api/v1/farmer/batches/batch-001', [
                'jumlah_karung' => 25,
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code'    => 'FORBIDDEN',
            ]);
    }

    // ========================================================================
    // 7.5: DELETE /api/v1/farmer/batches/{batchId} — Hapus Batch Draft
    // ========================================================================

    public function test_delete_batch_draft_returns_200()
    {
        $batch = Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'batch_id'  => 'batch-001',
            'status'    => 'draft',
        ]);

        $response = $this->actingAs($this->farmer)
            ->deleteJson('/api/v1/farmer/batches/batch-001');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code'    => 'SUCCESS_DELETE',
                'message' => 'Batch berhasil dihapus',
            ])
            ->assertJsonStructure([
                'data' => [
                    'deleted_batch_id',
                    'deleted_batch_code',
                    'deleted_at',
                ],
            ]);

        // Verifikasi batch sudah hilang dari database
        $this->assertDatabaseMissing('batches', [
            'batch_id' => 'batch-001',
        ]);
    }

    public function test_delete_batch_non_draft_processing_returns_400()
    {
        Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'batch_id'  => 'batch-001',
            'status'    => 'processing',
        ]);

        $response = $this->actingAs($this->farmer)
            ->deleteJson('/api/v1/farmer/batches/batch-001');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'code'    => 'INVALID_BATCH_STATUS_TRANSITION',
            ]);
    }

    public function test_delete_batch_non_draft_ready_returns_400()
    {
        Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'batch_id'  => 'batch-001',
            'status'    => 'ready',
        ]);

        $response = $this->actingAs($this->farmer)
            ->deleteJson('/api/v1/farmer/batches/batch-001');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'code'    => 'INVALID_BATCH_STATUS_TRANSITION',
            ]);
    }

    public function test_delete_batch_not_found()
    {
        $response = $this->actingAs($this->farmer)
            ->deleteJson('/api/v1/farmer/batches/invalid-batch-id');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code'    => 'NOT_FOUND',
            ]);
    }

    public function test_delete_batch_data_isolation_other_farmer_returns_403_or_404()
    {
        Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'batch_id'  => 'batch-001',
            'status'    => 'draft',
        ]);

        $response = $this->actingAs($this->farmer2)
            ->deleteJson('/api/v1/farmer/batches/batch-001');

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

        // Verifikasi batch TIDAK terhapus (masih ada di database)
        $this->assertDatabaseHas('batches', ['batch_id' => 'batch-001']);
    }

    public function test_delete_batch_unauthorized_without_auth()
    {
        $response = $this->deleteJson('/api/v1/farmer/batches/batch-001');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code'    => 'UNAUTHORIZED',
            ]);
    }

    public function test_delete_batch_forbidden_with_exporter_role()
    {
        Batch::factory()->create([
            'farmer_id' => $this->farmer->id,
            'batch_id'  => 'batch-001',
            'status'    => 'draft',
        ]);

        $response = $this->actingAs($this->exporter)
            ->deleteJson('/api/v1/farmer/batches/batch-001');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code'    => 'FORBIDDEN',
            ]);
    }

    // ========================================================================
    // Helper Methods
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
}

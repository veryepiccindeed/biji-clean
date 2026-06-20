<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\BatchLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * HashGenerateTest
 *
 * Test suite untuk endpoint POST /api/v1/hashes/generate.
 *
 * Alur lengkap yang diuji:
 *   REGISTER → LOGIN (dapat token) → Generate Certificate PDF → HASH (Kontrak + IoT)
 *
 * Sumber data:
 *   - Hash Kontrak  : certificate_pdf_path dari ExporterController::generateCertificate()
 *   - Hash IoT      : BatchLog (source='iot') dari FarmerBatchController::logs()
 *
 * Catatan setup:
 *   - Untuk test yang fokus pada HASH (bukan auth), token dibuat langsung via createToken()
 *     agar tidak ada session state Sanctum yang bocor antar request.
 *   - Untuk test yang menguji alur LOGIN → HASH, login dilakukan dalam body test itu sendiri,
 *     bukan di setUp().
 */
class HashGenerateTest extends TestCase
{
    use RefreshDatabase;

    // ─── Kredensial exporter ──────────────────────────────────────
    private const EMAIL    = 'budi.eksportir@biji.id';
    private const PASSWORD = 'ExportPass123!';
    private const NAME     = 'Budi Eksportir';

    // ─── Shared state (dipakai oleh test-test hash) ───────────────
    private User   $exporter;
    private string $token;   // plainTextToken dari createToken()
    private Batch  $batch;

    // ═══════════════════════════════════════════════════════════════
    //  SETUP & HELPERS
    // ═══════════════════════════════════════════════════════════════

    protected function setUp(): void
    {
        parent::setUp();

        // Mencegah error 'Disk [supabase] does not have a configured driver'
        Storage::fake('supabase');

        // Buat user exporter
        $this->exporter = User::factory()->create([
            'name'     => self::NAME,
            'email'    => self::EMAIL,
            'password' => bcrypt(self::PASSWORD),
            'role'     => 'exporter',
        ]);

        // Buat token LANGSUNG (bukan lewat login API) agar tidak ada session state
        // yang bocor dan menyebabkan Sanctum mengembalikan TransientToken.
        $this->token = $this->exporter->createToken('test_token')->plainTextToken;

        // Batch default milik exporter
        $this->batch = $this->createBatch();
    }

    /**
     * Buat batch dengan attribute tertentu.
     */
    private function createBatch(?string $pdfPath = null, string $blockchainStatus = 'none'): Batch
    {
        return Batch::factory()->create([
            'batch_id'             => 'PROD-2026-001',
            'batch_code'           => 'BJI-BUDI-260606',
            'exporter_id'          => $this->exporter->id,
            'acquired_by'          => $this->exporter->id,
            'status'               => 'acquired',
            'blockchain_status'    => $blockchainStatus,
            'certificate_pdf_path' => $pdfPath,
            'price'                => 15000000,
            'variety'              => 'Arabica',
        ]);
    }

    /**
     * Buat log sensor IoT untuk batch.
     */
    private function createIotLog(Batch $batch, float $temp, float $humidity, int $minutesAgo = 30): BatchLog
    {
        return BatchLog::create([
            'batch_id'    => $batch->batch_id,
            'source'      => 'iot',
            'log_type'    => 'monitoring',
            'temperature' => $temp,
            'humidity'    => $humidity,
            'created_at'  => now()->subMinutes($minutesAgo),
            'updated_at'  => now()->subMinutes($minutesAgo),
        ]);
    }

    /**
     * Kirim POST /api/v1/hashes/generate dengan Bearer token yang sudah ada.
     */
    private function requestHash(array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/hashes/generate', array_merge(
                ['batch_id' => 'PROD-2026-001'],
                $extra
            ));
    }

    /**
     * Login via API dan kembalikan access_token.
     * Digunakan hanya oleh test yang menguji alur login itu sendiri.
     */
    private function loginViaApi(string $email = self::EMAIL, string $password = self::PASSWORD): string
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email'       => $email,
            'password'    => $password,
            'remember_me' => false,
        ]);

        $response->assertStatus(200)->assertJson(['code' => 'SUCCESS']);

        return $response->json('data.access_token');
    }

    /**
     * Register via API dan kembalikan access_token.
     */
    private function registerViaApi(string $email, string $name = 'Test Exporter'): string
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name'                  => $name,
            'email'                 => $email,
            'password'              => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'role'                  => 'exporter',
        ]);

        $response->assertStatus(201)->assertJson(['code' => 'SUCCESS_CREATE']);

        return $response->json('data.access_token');
    }

    // ═══════════════════════════════════════════════════════════════
    //  ALUR 1 – REGISTER → TOKEN LANGSUNG DIPAKAI UNTUK HASH
    // ═══════════════════════════════════════════════════════════════

    /**
     * Pengguna baru mendaftar → dapat token dari register → langsung generate hash.
     * Token dari register harus bisa digunakan untuk mengakses endpoint hash.
     */
    #[Test]
    public function flow_register_then_use_token_to_generate_hash(): void
    {
        // STEP 1: Register exporter baru (berbeda dari setUp)
        $newEmail = 'new.exporter@biji.id';
        $registerToken = $this->registerViaApi($newEmail, 'New Exporter');

        $this->assertDatabaseHas('users', ['email' => $newEmail, 'role' => 'exporter']);

        // STEP 2: Buat batch untuk exporter baru
        $newExporter = User::where('email', $newEmail)->first();
        Batch::factory()->create([
            'batch_id'    => 'PROD-NEW-001',
            'exporter_id' => $newExporter->id,
            'acquired_by' => $newExporter->id,
            'status'      => 'acquired',
            'price'       => 12000000,
        ]);

        // STEP 3: Gunakan token dari register untuk generate hash
        $response = $this->withHeader('Authorization', "Bearer {$registerToken}")
            ->postJson('/api/v1/hashes/generate', ['batch_id' => 'PROD-NEW-001']);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'code' => 'SUCCESS'])
            ->assertJsonStructure([
                'data' => ['batch_id', 'batch_code', 'contract_hash', 'iot_hash'],
            ]);
    }

    // ═══════════════════════════════════════════════════════════════
    //  ALUR 2 – LOGIN → TOKEN DIPAKAI UNTUK HASH
    // ═══════════════════════════════════════════════════════════════

    /**
     * Full login flow: masuk dengan kredensial → dapat token → generate hash.
     */
    #[Test]
    public function flow_login_then_generate_hash(): void
    {
        // STEP 1: Login via API (exporter sudah ada dari setUp)
        $loginToken = $this->loginViaApi();
        $this->assertNotEmpty($loginToken);

        // STEP 2: Generate hash menggunakan token dari login
        $response = $this->withHeader('Authorization', "Bearer {$loginToken}")
            ->postJson('/api/v1/hashes/generate', ['batch_id' => 'PROD-2026-001']);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code'    => 'SUCCESS',
                'message' => 'Hash berhasil di-generate.',
            ]);
    }

    /**
     * Kredensial salah → login gagal → tidak dapat token → tidak bisa hash.
     */
    #[Test]
    public function flow_wrong_password_cannot_login_and_cannot_hash(): void
    {
        // STEP 1: Login dengan password salah
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email'       => self::EMAIL,
            'password'    => 'PasswordSalah999!',
            'remember_me' => false,
        ]);

        $loginResponse->assertStatus(401)->assertJson(['code' => 'UNAUTHORIZED']);

        // STEP 2: Tidak dapat token → tidak bisa hash
        $hashResponse = $this->postJson('/api/v1/hashes/generate', [
            'batch_id' => 'PROD-2026-001',
        ]);
        $hashResponse->assertStatus(401);
    }

    /**
     * Token tidak valid → hash ditolak 401.
     */
    #[Test]
    public function flow_invalid_token_cannot_generate_hash(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer token-palsu-tidak-valid')
            ->postJson('/api/v1/hashes/generate', ['batch_id' => 'PROD-2026-001']);

        $response->assertStatus(401);
    }

    /**
     * Tanpa Authorization header → hash ditolak 401.
     */
    #[Test]
    public function flow_without_token_cannot_generate_hash(): void
    {
        $response = $this->postJson('/api/v1/hashes/generate', [
            'batch_id' => 'PROD-2026-001',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Setelah logout, token lama tidak bisa dipakai generate hash.
     * Menggunakan user baru agar tidak ada token lain dari setUp() yang mengganggu.
     */
    #[Test]
    public function flow_after_logout_old_token_cannot_generate_hash(): void
    {
        // Buat user baru khusus test ini (bukan $this->exporter yang punya token dari setUp)
        $freshUser = User::factory()->create([
            'role'     => 'exporter',
            'password' => bcrypt(self::PASSWORD),
        ]);

        // Buat batch milik user baru
        $freshBatch = Batch::factory()->create([
            'batch_id'    => 'PROD-LOGOUT-TEST',
            'exporter_id' => $freshUser->id,
            'acquired_by' => $freshUser->id,
            'status'      => 'acquired',
        ]);

        // Buat satu token untuk user ini
        $freshToken = $freshUser->createToken('logout_test_token')->plainTextToken;

        // STEP 1: Verifikasi token masih aktif
        $this->withHeader('Authorization', "Bearer {$freshToken}")
            ->postJson('/api/v1/hashes/generate', ['batch_id' => 'PROD-LOGOUT-TEST'])
            ->assertStatus(200);

        // STEP 2: Logout → hapus token dari DB
        $this->withHeader('Authorization', "Bearer {$freshToken}")
            ->postJson('/api/v1/auth/logout')
            ->assertStatus(200)
            ->assertJson(['success' => true, 'code' => 'SUCCESS']);

        // STEP 3: Token lama sudah tidak ada di DB → harus 401
        $this->withHeader('Authorization', "Bearer {$freshToken}")
            ->postJson('/api/v1/hashes/generate', ['batch_id' => 'PROD-LOGOUT-TEST'])
            ->assertStatus(401);
    }

    // ═══════════════════════════════════════════════════════════════
    //  ALUR 3 – TOKEN AKTIF → GENERATE CERTIFICATE PDF → HASH
    // ═══════════════════════════════════════════════════════════════

    /**
     * Simulasi: ExporterController::generateCertificate() menyimpan path PDF ke DB.
     * Hash kontrak harus mencerminkan certificate_pdf_path yang baru.
     */
    #[Test]
    public function flow_generate_certificate_then_hash_reflects_pdf(): void
    {
        // STEP 1: Sebelum generate certificate → pdf_file_exists = false
        $responseBefore = $this->requestHash();
        $responseBefore->assertStatus(200);
        $this->assertNull($responseBefore->json('data.contract_hash.certificate_pdf_path'));
        $this->assertFalse($responseBefore->json('data.contract_hash.pdf_file_exists'));
        $hashBefore = $responseBefore->json('data.contract_hash.hash_value');

        // STEP 2: ExporterController::generateCertificate() → simpan path PDF & set blockchain_status
        $pdfPath = "batches/certificates/cert-{$this->batch->id}.pdf";
        $this->batch->update([
            'certificate_pdf_path' => $pdfPath,
            'blockchain_status'    => 'pending',
        ]);

        // STEP 3: Hash setelah generate certificate
        $responseAfter = $this->requestHash();
        $responseAfter->assertStatus(200);
        $hashAfter = $responseAfter->json('data.contract_hash.hash_value');

        $this->assertEquals($pdfPath,  $responseAfter->json('data.contract_hash.certificate_pdf_path'));
        $this->assertEquals('pending', $responseAfter->json('data.contract_hash.blockchain_status'));
        $this->assertFalse($responseAfter->json('data.contract_hash.pdf_file_exists')); // file fisik belum ada
        $this->assertNotEquals($hashBefore, $hashAfter,
            'Hash kontrak harus berubah setelah certificate_pdf_path di-set');
    }

    /**
     * Hash berubah lagi saat blockchain_status berubah dari 'pending' ke 'published'.
     */
    #[Test]
    public function flow_hash_changes_when_blockchain_status_published(): void
    {
        $this->batch->update([
            'certificate_pdf_path' => "batches/certificates/cert-{$this->batch->id}.pdf",
            'blockchain_status'    => 'pending',
        ]);
        $hashPending = $this->requestHash()->json('data.contract_hash.hash_value');

        // ExporterController::publishCertificate()
        $this->batch->update(['blockchain_status' => 'published']);
        $hashPublished = $this->requestHash()->json('data.contract_hash.hash_value');

        $this->assertNotEquals($hashPending, $hashPublished,
            'Hash harus berubah saat blockchain_status berubah ke published');
    }

    /**
     * Hash kontrak bersifat deterministik: data sama → hash identik.
     */
    #[Test]
    public function flow_contract_hash_is_deterministic(): void
    {
        $hash1 = $this->requestHash()->json('data.contract_hash.hash_value');
        $hash2 = $this->requestHash()->json('data.contract_hash.hash_value');

        $this->assertEquals($hash1, $hash2,
            'Hash kontrak harus deterministik untuk data yang sama');
    }

    /**
     * Payload hash kontrak harus berisi field-field utama dari batch.
     */
    #[Test]
    public function flow_contract_hash_payload_contains_expected_fields(): void
    {
        $response = $this->requestHash();
        $response->assertStatus(200)
            ->assertJsonPath('data.contract_hash.hash_type',      'contract')
            ->assertJsonPath('data.contract_hash.hash_algorithm', 'sha256');

        $payload = $response->json('data.contract_hash.payload');

        $this->assertEquals('PROD-2026-001',     $payload['batch_id']);
        $this->assertEquals('BJI-BUDI-260606',   $payload['batch_code']);
        $this->assertEquals($this->exporter->id, $payload['exporter_id']);
        $this->assertArrayHasKey('price',                $payload);
        $this->assertArrayHasKey('blockchain_status',    $payload);
        $this->assertArrayHasKey('certificate_pdf_path', $payload);

        // Format hash: SHA-256 = 64 karakter hex
        $hashValue = $response->json('data.contract_hash.hash_value');
        $this->assertEquals(64, strlen($hashValue));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hashValue);
    }

    // ═══════════════════════════════════════════════════════════════
    //  ALUR 4 – TOKEN AKTIF → SENSOR IoT MASUK → HASH IoT
    // ═══════════════════════════════════════════════════════════════

    /**
     * Setiap kali sensor IoT mengirim data baru → hash IoT berubah.
     */
    #[Test]
    public function flow_iot_sensor_data_recorded_then_hashed(): void
    {
        $period = [
            'period_start' => now()->subDay()->toDateTimeString(),
            'period_end'   => now()->addMinute()->toDateTimeString(),
        ];

        // Sebelum ada log sensor
        $state0 = $this->requestHash($period)->json('data.iot_hash');
        $this->assertEquals(0, $state0['log_count']);

        // Sensor IoT → data pertama masuk
        $this->createIotLog($this->batch, 26.5, 62.0, minutesAgo: 60);
        $state1 = $this->requestHash($period)->json('data.iot_hash');
        $this->assertEquals(1, $state1['log_count']);
        $this->assertNotEquals($state0['hash_value'], $state1['hash_value'],
            'Hash IoT harus berubah setelah data sensor pertama masuk');

        // Sensor IoT → data kedua masuk
        $this->createIotLog($this->batch, 30.0, 68.0, minutesAgo: 30);
        $state2 = $this->requestHash($period)->json('data.iot_hash');
        $this->assertEquals(2, $state2['log_count']);
        $this->assertNotEquals($state1['hash_value'], $state2['hash_value'],
            'Hash IoT harus berubah setelah data sensor kedua masuk');
    }

    /**
     * Log manual (source != 'iot') tidak boleh mempengaruhi hash IoT.
     */
    #[Test]
    public function flow_manual_logs_do_not_affect_iot_hash(): void
    {
        $this->createIotLog($this->batch, 28.0, 65.0, minutesAgo: 60);

        $period = [
            'period_start' => now()->subDay()->toDateTimeString(),
            'period_end'   => now()->addMinute()->toDateTimeString(),
        ];
        $hashBefore = $this->requestHash($period)->json('data.iot_hash.hash_value');

        // Petani mencatat manual → tidak boleh masuk ke hash IoT
        BatchLog::create([
            'batch_id'    => $this->batch->batch_id,
            'source'      => 'manual',
            'log_type'    => 'note',
            'temperature' => 35.0,
            'humidity'    => 80.0,
            'created_at'  => now()->subMinutes(30),
            'updated_at'  => now()->subMinutes(30),
        ]);

        $hashAfter = $this->requestHash($period)->json('data.iot_hash.hash_value');

        $this->assertEquals($hashBefore, $hashAfter,
            'Hash IoT tidak boleh berubah akibat log manual');
    }

    /**
     * Filter periode: hanya log dalam rentang yang masuk ke hash.
     */
    #[Test]
    public function flow_iot_hash_respects_period_filter(): void
    {
        $this->createIotLog($this->batch, 25.0, 60.0, minutesAgo: 2 * 24 * 60); // 2 hari lalu (di luar)
        $this->createIotLog($this->batch, 29.0, 66.0, minutesAgo: 60);           // 1 jam lalu (dalam)

        $response = $this->requestHash([
            'period_start' => now()->subDay()->toDateTimeString(),
            'period_end'   => now()->addMinute()->toDateTimeString(),
        ]);

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('data.iot_hash.log_count'),
            'Hanya log dalam periode yang boleh masuk ke hash IoT');
    }

    /**
     * Statistik hash IoT (avg, max, min) dihitung dengan benar.
     */
    #[Test]
    public function flow_iot_hash_stats_are_correct(): void
    {
        $readings = [
            ['temperature' => 26.0, 'humidity' => 60.0, 'minutesAgo' => 120],
            ['temperature' => 28.0, 'humidity' => 64.0, 'minutesAgo' => 90],
            ['temperature' => 30.0, 'humidity' => 68.0, 'minutesAgo' => 60],
        ];
        foreach ($readings as $r) {
            $this->createIotLog($this->batch, $r['temperature'], $r['humidity'], $r['minutesAgo']);
        }

        $stats = $this->requestHash([
            'period_start' => now()->subDay()->toDateTimeString(),
            'period_end'   => now()->addMinute()->toDateTimeString(),
        ])->json('data.iot_hash.stats');

        $this->assertEquals(28.0, $stats['avg_temperature']); // (26+28+30)/3
        $this->assertEquals(64.0, $stats['avg_humidity']);     // (60+64+68)/3
        $this->assertEquals(30.0, $stats['max_temperature']);
        $this->assertEquals(26.0, $stats['min_temperature']);
    }

    // ═══════════════════════════════════════════════════════════════
    //  ALUR 5 – FULL END-TO-END (Login → PDF → IoT → Hash → Tamper)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Alur paling lengkap:
     *   1. Login → dapat token
     *   2. Generate hash awal (sebelum PDF & IoT)
     *   3. Simulate ExporterController::generateCertificate()
     *   4. Simulate data sensor IoT masuk
     *   5. Generate hash final → verifikasi perubahan
     *   6. Manipulasi data → verifikasi tamper detection
     */
    #[Test]
    public function flow_full_login_pdf_iot_hash_tamper_detection(): void
    {
        // ── STEP 1: Login ────────────────────────────────────────
        $loginToken = $this->loginViaApi();
        $this->assertNotEmpty($loginToken, 'Token login tidak boleh kosong');

        $authHeader = ['Authorization' => "Bearer {$loginToken}"];

        // ── STEP 2: Hash awal (sebelum PDF & IoT) ────────────────
        $init = $this->withHeaders($authHeader)
            ->postJson('/api/v1/hashes/generate', ['batch_id' => 'PROD-2026-001']);

        $init->assertStatus(200);
        $hashContractInit = $init->json('data.contract_hash.hash_value');
        $hashIotInit      = $init->json('data.iot_hash.hash_value');

        $this->assertNull($init->json('data.contract_hash.certificate_pdf_path'));
        $this->assertEquals(0, $init->json('data.iot_hash.log_count'));

        // ── STEP 3: ExporterController::generateCertificate() ────
        $pdfPath = "batches/certificates/cert-{$this->batch->id}.pdf";
        $this->batch->update([
            'certificate_pdf_path' => $pdfPath,
            'blockchain_status'    => 'pending',
        ]);

        // ── STEP 4: Data sensor IoT masuk (3 pembacaan) ──────────
        $iotData = [
            ['temperature' => 26.5, 'humidity' => 61.0, 'minutesAgo' => 120],
            ['temperature' => 28.0, 'humidity' => 64.5, 'minutesAgo' => 90],
            ['temperature' => 29.5, 'humidity' => 67.0, 'minutesAgo' => 60],
        ];
        foreach ($iotData as $d) {
            $this->createIotLog($this->batch, $d['temperature'], $d['humidity'], $d['minutesAgo']);
        }

        // ── STEP 5: Hash final ───────────────────────────────────
        $final = $this->withHeaders($authHeader)
            ->postJson('/api/v1/hashes/generate', [
                'batch_id'     => 'PROD-2026-001',
                'period_start' => now()->subDay()->toDateTimeString(),
                'period_end'   => now()->addMinute()->toDateTimeString(),
            ]);

        $final->assertStatus(200)->assertJson(['success' => true, 'code' => 'SUCCESS']);

        $hashContractFinal = $final->json('data.contract_hash.hash_value');
        $hashIotFinal      = $final->json('data.iot_hash.hash_value');

        // Hash kontrak berubah setelah PDF di-generate
        $this->assertNotEquals($hashContractInit, $hashContractFinal,
            'Hash kontrak harus berubah setelah certificate PDF di-generate');

        // Hash IoT berubah setelah ada data sensor
        $this->assertNotEquals($hashIotInit, $hashIotFinal,
            'Hash IoT harus berubah setelah data sensor masuk');

        // Format hash SHA-256 benar
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hashContractFinal);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hashIotFinal);

        // Data kontrak dalam response benar
        $this->assertEquals($pdfPath,  $final->json('data.contract_hash.certificate_pdf_path'));
        $this->assertEquals('pending', $final->json('data.contract_hash.blockchain_status'));

        // Data IoT dalam response benar
        $this->assertEquals(3, $final->json('data.iot_hash.log_count'));
        $stats = $final->json('data.iot_hash.stats');
        $this->assertEquals(28.0, $stats['avg_temperature']); // (26.5+28+29.5)/3

        // ── STEP 6: Tamper Detection ──────────────────────────────
        $this->batch->update(['price' => 1]); // Manipulasi harga

        $tampered = $this->withHeaders($authHeader)
            ->postJson('/api/v1/hashes/generate', ['batch_id' => 'PROD-2026-001']);

        $this->assertNotEquals(
            $hashContractFinal,
            $tampered->json('data.contract_hash.hash_value'),
            'Hash kontrak HARUS berubah saat data dimanipulasi (tamper detection aktif)'
        );
    }

    // ═══════════════════════════════════════════════════════════════
    //  ALUR 6 – DATA ISOLATION & SECURITY
    // ═══════════════════════════════════════════════════════════════

    /**
     * Exporter lain tidak bisa generate hash untuk batch yang bukan miliknya.
     */
    #[Test]
    public function flow_exporter_cannot_hash_other_exporter_batch(): void
    {
        // Buat exporter kedua dan login
        $otherExporter = User::factory()->create([
            'role'     => 'exporter',
            'password' => bcrypt(self::PASSWORD),
        ]);

        $otherLoginResponse = $this->postJson('/api/v1/auth/login', [
            'email'       => $otherExporter->email,
            'password'    => self::PASSWORD,
            'remember_me' => false,
        ]);
        $otherToken = $otherLoginResponse->json('data.access_token');

        // Coba generate hash untuk batch milik exporter PERTAMA
        $response = $this->withHeader('Authorization', "Bearer {$otherToken}")
            ->postJson('/api/v1/hashes/generate', [
                'batch_id' => 'PROD-2026-001', // milik $this->exporter
            ]);

        $response->assertStatus(404)
            ->assertJson(['success' => false, 'code' => 'NOT_FOUND']);
    }

    /**
     * Validasi: batch_id wajib diisi.
     */
    #[Test]
    public function flow_validation_batch_id_required(): void
    {
        $this->requestHash(['batch_id' => '']) // override batch_id dengan empty
            ->assertStatus(422)
            ->assertJson(['success' => false, 'code' => 'VALIDATION_ERROR']);
    }

    /**
     * Validasi: batch tidak ditemukan → 404.
     */
    #[Test]
    public function flow_validation_nonexistent_batch_returns_not_found(): void
    {
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/hashes/generate', ['batch_id' => 'BATCH-TIDAK-ADA'])
            ->assertStatus(404)
            ->assertJson(['success' => false, 'code' => 'NOT_FOUND']);
    }

    /**
     * Validasi: format tanggal salah → 422 VALIDATION_ERROR.
     */
    #[Test]
    public function flow_validation_invalid_date_format(): void
    {
        $this->requestHash([
            'period_start' => 'ini-bukan-tanggal',
            'period_end'   => 'juga-bukan-tanggal',
        ])
        ->assertStatus(422)
        ->assertJson(['success' => false, 'code' => 'VALIDATION_ERROR']);
    }

    // ═══════════════════════════════════════════════════════════════
    //  TEST CUSTOM: Cek Apakah Kontrak & IoT Sudah Di-Hash
    // ═══════════════════════════════════════════════════════════════

    /**
     * Test case untuk mengecek secara eksplisit apakah Kontrak dan Data IoT 
     * berhasil di-hash dan datanya masuk ke dalam payload hash.
     */
    #[Test]
    public function flow_check_if_contract_and_iot_data_are_hashed(): void
    {
        // 1. Siapkan data: Set PDF Kontrak
        $pdfPath = "batches/certificates/cert-{$this->batch->id}.pdf";
        $this->batch->update([
            'certificate_pdf_path' => $pdfPath,
            'blockchain_status'    => 'published',
        ]);

        // 2. Siapkan data: Masukkan 2 data sensor IoT
        $this->createIotLog($this->batch, 25.5, 60.0, minutesAgo: 30);
        $this->createIotLog($this->batch, 26.0, 62.0, minutesAgo: 15);

        // 3. Request ke endpoint Generate Hash
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/hashes/generate', [
                'batch_id' => 'PROD-2026-001'
            ]);

        // 4. Pastikan response sukses (200 OK)
        $response->assertStatus(200);

        // 5. Assert (Pengecekan) Hash Kontrak
        $contractHash = $response->json('data.contract_hash');
        
        $this->assertNotEmpty($contractHash['hash_value'], 'Hash Kontrak belum digenerate/kosong');
        $this->assertEquals($pdfPath, $contractHash['certificate_pdf_path'], 'Kontrak PDF belum dimasukkan ke hash');
        $this->assertEquals('published', $contractHash['blockchain_status'], 'Status blockchain salah');

        // 6. Assert (Pengecekan) Hash IoT
        $iotHash = $response->json('data.iot_hash');
        
        $this->assertNotEmpty($iotHash['hash_value'], 'Hash IoT belum digenerate/kosong');
        $this->assertEquals(2, $iotHash['log_count'], 'Data sensor IoT belum masuk ke perhitungan Hash');
        
        // 7. Verifikasi payload hash benar-benar menggunakan algoritma sha256
        $this->assertEquals('sha256', $contractHash['hash_algorithm']);
        $this->assertEquals('sha256', $iotHash['hash_algorithm']);
    }

    /**
     * Test case untuk men-generate hash dan menampilkannya langsung di terminal.
     */
    #[Test]
    public function flow_print_generated_hash_to_terminal(): void
    {
        // 1. Siapkan data: Set PDF Kontrak
        $pdfPath = "batches/certificates/cert-{$this->batch->id}.pdf";
        $this->batch->update([
            'certificate_pdf_path' => $pdfPath,
            'blockchain_status'    => 'published',
        ]);

        // 2. Siapkan data: Masukkan 2 data sensor IoT
        $this->createIotLog($this->batch, 25.5, 60.0, minutesAgo: 30);
        $this->createIotLog($this->batch, 26.0, 62.0, minutesAgo: 15);

        // 3. Request ke endpoint Generate Hash
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/hashes/generate', [
                'batch_id' => 'PROD-2026-001'
            ]);

        // 4. Pastikan response sukses
        $response->assertStatus(200);

        $contractHash = $response->json('data.contract_hash.hash_value');
        $iotHash = $response->json('data.iot_hash.hash_value');

        // 5. Cetak ke terminal agar bisa dilihat user
        dump([
            'Batch ID' => 'PROD-2026-001',
            'Hash Kontrak' => $contractHash,
            'Hash IoT' => $iotHash
        ]);

        $this->assertNotEmpty($contractHash);
        $this->assertNotEmpty($iotHash);
    }
}

<?php

namespace Tests\Feature\Farmer;

use App\Models\Batch;
use App\Models\BatchPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FarmerOfflineSyncTest — Test Case untuk Modul Sinkronisasi Offline Petani (API Contract V2.1)
 *
 * Scope: 1 endpoint sync offline farmer
 *   - POST /api/v1/farmer/sync — Sinkronkan data offline ke server (15.1)
 *
 * Reference: API_CONTRACT_V2_FARMER.md Section 15 (Modul Sinkronisasi Offline)
 *
 * Business Rules V2.1 yang ditest:
 * - Endpoint bersifat idempotent via client_sync_id (cegah duplikasi pengiriman)
 * - Field batch_logs DIHAPUS di V2.1 — log 100% dari IoT, tidak bisa sync offline
 * - Hanya batch_photos yang bisa diunggah petani saat offline (base64 encoded)
 * - Partial failure: response tetap 200 dengan failed_count > 0 dan detail per item
 * - Client WAJIB hapus data lokal setelah sync sukses
 * - Batch yang sudah dihapus selama offline → OFFLINE_SYNC_CONFLICT (409)
 * - Data isolation: petani hanya bisa sync foto ke batch miliknya sendiri
 */
class FarmerOfflineSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $farmer;

    private User $farmer2;

    private User $exporter;

    private string $validBase64Jpeg;

    private string $validBase64Png;

    private array $authTokens = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->farmer = User::factory()->create([
            'role' => 'farmer',
            'phone' => '+62 812-3456-7890',
            'phone_verified' => true,
            'location' => 'Tana Toraja, Sulawesi Selatan',
            'coordinates' => '-3.0701, 119.8923',
            'profile_completion' => 100,
        ]);
        $this->farmer2 = User::factory()->create([
            'role' => 'farmer',
            'phone' => '+62 813-9876-5432',
            'phone_verified' => true,
            'location' => 'Enrekang, Sulawesi Selatan',
            'coordinates' => '-3.4023, 119.8432',
            'profile_completion' => 100,
        ]);
        $this->exporter = User::factory()->create([
            'role' => 'exporter',
            'email' => 'exporter@biji.local',
        ]);

        // Base64 minimal JPEG (1x1 pixel merah)
        $this->validBase64Jpeg = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAMCAgICAgMCAgIDAwMDBAYEBAQEBAgGBgUGCQgKCgkICQkKDA8MCgsOCwkJDRENDg8QEBEQCgwSFBIQMQwSBQ0P/2wBDAQMDAwQDBAgEBAgQCwkLEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBD/wAARCAABAAEDASIAAhEBAxEB/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/xAAUAQEAAAAAAAAAAAAAAAAAAAAA/8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAwDAQACEQMRAD8AKwA//9k=';

        // Base64 minimal PNG (1x1 pixel)
        $this->validBase64Png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPj/HwADBwIAMCbHYQAAAABJRU5ErkJggg==';
    }

    // =========================================================================
    // HELPER
    // =========================================================================

    private function createBatchForFarmer(User $farmer, string $status = 'iot_installed', array $overrides = []): Batch
    {
        return Batch::factory()->create(array_merge([
            'farmer_id' => $farmer->id,
            'status' => $status,
            'name' => 'Batch Test '.$farmer->id,
        ], $overrides));
    }

    private function validSyncPayload(string $clientSyncId = 'sync-client-uuid-001', array $photos = []): array
    {
        $batch = $this->createBatchForFarmer($this->farmer);

        $defaultPhotos = $photos ?: [[
            'client_temp_id' => 'temp-photo-001',
            'batch_id' => $batch->id,
            'filename' => 'offline-photo-1.jpg',
            'photo_data_base64' => $this->validBase64Jpeg,
            'note' => 'Foto pengeringan',
            'created_at_local' => '2026-06-03T09:00:00Z',
        ]];

        return [
            'client_offline_since' => '2026-06-03T08:00:00Z',
            'client_sync_id' => $clientSyncId,
            'batch_photos' => $defaultPhotos,
        ];
    }

    private function authHeaders(User $user): array
    {
        $userId = $user->id;
        if (! isset($this->authTokens[$userId])) {
            auth()->forgetUser();
            try {
                auth('sanctum')->forgetUser();
            } catch (\Exception $e) {
            }

            $this->authTokens[$userId] = $user->createToken('test-token')->plainTextToken;
        }

        return ['Authorization' => 'Bearer '.$this->authTokens[$userId]];
    }

    private function syncUrl(): string
    {
        return '/api/v1/farmer/sync';
    }

    // =========================================================================
    // 1. HAPPY PATH — Sync Berhasil
    // =========================================================================

    /** Sync 1 foto offline — sukses penuh (semua item berhasil) */
    public function test_sync_single_photo_success(): void
    {
        $payload = $this->validSyncPayload('sync-single-001');

        $response = $this->postJson($this->syncUrl(), $payload, $this->authHeaders($this->farmer));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
                'message' => 'Sinkronisasi offline berhasil',
            ])
            ->assertJsonStructure([
                'success', 'code', 'message',
                'data' => [
                    'sync_id',
                    'client_sync_id',
                    'results' => [
                        'photos' => [
                            'total_sent',
                            'success_count',
                            'failed_count',
                            'items',
                        ],
                    ],
                    'synced_at',
                ],
                'timestamp',
            ])
            ->assertJsonPath('data.client_sync_id', 'sync-single-001')
            ->assertJsonPath('data.results.photos.total_sent', 1)
            ->assertJsonPath('data.results.photos.success_count', 1)
            ->assertJsonPath('data.results.photos.failed_count', 0);

        // Pastikan client_temp_id ada di response items
        $response->assertJsonPath('data.results.photos.items.0.client_temp_id', 'temp-photo-001');
        // Pastikan server_id dan url tersedia
        $response->assertJsonPath('data.results.photos.items.0.status', 'success');
    }

    /** Sync multiple foto sekaligus (3 foto ke batch yang sama) */
    public function test_sync_multiple_photos_success(): void
    {
        $batch = $this->createBatchForFarmer($this->farmer);

        $payload = [
            'client_offline_since' => '2026-06-03T07:00:00Z',
            'client_sync_id' => 'sync-multi-001',
            'batch_photos' => [
                [
                    'client_temp_id' => 'temp-photo-010',
                    'batch_id' => $batch->id,
                    'filename' => 'offline-photo-10.jpg',
                    'photo_data_base64' => $this->validBase64Jpeg,
                    'note' => 'Foto pertama',
                    'created_at_local' => '2026-06-03T07:30:00Z',
                ],
                [
                    'client_temp_id' => 'temp-photo-011',
                    'batch_id' => $batch->id,
                    'filename' => 'offline-photo-11.png',
                    'photo_data_base64' => $this->validBase64Png,
                    'note' => 'Foto kedua',
                    'created_at_local' => '2026-06-03T08:00:00Z',
                ],
                [
                    'client_temp_id' => 'temp-photo-012',
                    'batch_id' => $batch->id,
                    'filename' => 'offline-photo-12.jpg',
                    'photo_data_base64' => $this->validBase64Jpeg,
                    'note' => 'Foto ketiga',
                    'created_at_local' => '2026-06-03T08:30:00Z',
                ],
            ],
        ];

        $response = $this->postJson($this->syncUrl(), $payload, $this->authHeaders($this->farmer));

        $response->assertStatus(200)
            ->assertJsonPath('data.results.photos.total_sent', 3)
            ->assertJsonPath('data.results.photos.success_count', 3)
            ->assertJsonPath('data.results.photos.failed_count', 0);

        // Verifikasi items berisi 3 entry
        $items = $response->json('data.results.photos.items');
        $this->assertCount(3, $items);

        // Pastikan semua status = success
        collect($items)->each(function ($item) {
            $this->assertEquals('success', $item['status']);
        });
    }

    /** Sync foto ke batch yang berbeda dalam 1 request */
    public function test_sync_photos_to_different_batches_success(): void
    {
        // Buat batch pertama (aktif)
        $batch1 = $this->createBatchForFarmer($this->farmer, 'iot_installed', ['name' => 'Batch Pertama']);

        // Buat batch kedua — petani bisa punya batch acquired + batch baru
        // Simulasi: batch pertama acquired, buat batch kedua
        $batch1->update(['status' => 'acquired']);
        $batch2 = $this->createBatchForFarmer($this->farmer, 'draft', ['name' => 'Batch Kedua']);

        $payload = [
            'client_offline_since' => '2026-06-03T06:00:00Z',
            'client_sync_id' => 'sync-cross-batch-001',
            'batch_photos' => [
                [
                    'client_temp_id' => 'temp-photo-a1',
                    'batch_id' => $batch2->id,
                    'filename' => 'photo-batch2-1.jpg',
                    'photo_data_base64' => $this->validBase64Jpeg,
                    'note' => 'Foto batch kedua',
                    'created_at_local' => '2026-06-03T06:30:00Z',
                ],
            ],
        ];

        $response = $this->postJson($this->syncUrl(), $payload, $this->authHeaders($this->farmer));

        $response->assertStatus(200)
            ->assertJsonPath('data.results.photos.success_count', 1);
    }

    /** Sync tanpa batch_photos (kosong) — tetap sukses karena field optional */
    public function test_sync_without_photos_success(): void
    {
        $payload = [
            'client_offline_since' => '2026-06-03T10:00:00Z',
            'client_sync_id' => 'sync-no-photos-001',
            'batch_photos' => [],
        ];

        $response = $this->postJson($this->syncUrl(), $payload, $this->authHeaders($this->farmer));

        $response->assertStatus(200)
            ->assertJsonPath('data.results.photos.total_sent', 0)
            ->assertJsonPath('data.results.photos.success_count', 0)
            ->assertJsonPath('data.results.photos.failed_count', 0);
    }

    /** Sync tanpa field batch_photos sama sekali — tetap sukses karena optional */
    public function test_sync_omit_photos_field_success(): void
    {
        $payload = [
            'client_offline_since' => '2026-06-03T10:00:00Z',
            'client_sync_id' => 'sync-omit-photos-001',
        ];

        $response = $this->postJson($this->syncUrl(), $payload, $this->authHeaders($this->farmer));

        $response->assertStatus(200)
            ->assertJsonPath('data.client_sync_id', 'sync-omit-photos-001');
    }

    /** Response structure lengkap — semua field yang diharapkan ada */
    public function test_sync_response_has_full_structure(): void
    {
        $payload = $this->validSyncPayload('sync-structure-001');

        $response = $this->postJson($this->syncUrl(), $payload, $this->authHeaders($this->farmer));

        $response->assertStatus(200);
        $data = $response->json('data');

        // Top-level data
        $this->assertNotNull($data['sync_id']);
        $this->assertEquals('sync-structure-001', $data['client_sync_id']);
        $this->assertNotNull($data['synced_at']);

        // Results.photos
        $photos = $data['results']['photos'];
        $this->assertArrayHasKey('total_sent', $photos);
        $this->assertArrayHasKey('success_count', $photos);
        $this->assertArrayHasKey('failed_count', $photos);
        $this->assertArrayHasKey('items', $photos);

        // Item fields
        $item = $photos['items'][0];
        $this->assertArrayHasKey('client_temp_id', $item);
        $this->assertArrayHasKey('server_id', $item);
        $this->assertArrayHasKey('status', $item);
        $this->assertArrayHasKey('url', $item);
    }

    // =========================================================================
    // 2. AUTENTIKASI & ROLE
    // =========================================================================

    /** Sync tanpa autentikasi → 401 Unauthorized */
    public function test_sync_unauthenticated_returns_401(): void
    {
        $payload = $this->validSyncPayload('sync-unauth-001');

        $response = $this->postJson($this->syncUrl(), $payload);

        $response->assertStatus(401);
    }

    /** Sync dengan role exporter → 403 Forbidden */
    public function test_sync_as_exporter_returns_403(): void
    {
        $payload = $this->validSyncPayload('sync-exporter-001');

        $response = $this->postJson($this->syncUrl(), $payload, $this->authHeaders($this->exporter));

        $response->assertStatus(403);
    }

    /** Sync dengan token farmer lain — foto sync ke batch milik farmer lain → harus ditolak per item */
    public function test_sync_photos_to_another_farmers_batch_fails_per_item(): void
    {
        $batchFarmer2 = $this->createBatchForFarmer($this->farmer2);

        $payload = [
            'client_offline_since' => '2026-06-03T08:00:00Z',
            'client_sync_id' => 'sync-other-farmer-001',
            'batch_photos' => [
                [
                    'client_temp_id' => 'temp-photo-other',
                    'batch_id' => $batchFarmer2->id,
                    'filename' => 'intruder-photo.jpg',
                    'photo_data_base64' => $this->validBase64Jpeg,
                    'note' => 'Coba upload ke batch orang lain',
                    'created_at_local' => '2026-06-03T09:00:00Z',
                ],
            ],
        ];

        $response = $this->postJson($this->syncUrl(), $payload, $this->authHeaders($this->farmer));

        // Partial failure pattern: 200 tapi failed_count > 0
        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('data.results.photos.total_sent'));
        $this->assertEquals(0, $response->json('data.results.photos.success_count'));
        $this->assertEquals(1, $response->json('data.results.photos.failed_count'));

        // Pastikan item gagal punya error detail
        $item = $response->json('data.results.photos.items.0');
        $this->assertEquals('failed', $item['status']);
        $this->assertArrayHasKey('error', $item);
    }

    // =========================================================================
    // 3. VALIDASI REQUEST
    // =========================================================================

    /** Body kosong → 422 Validation Error */
    public function test_sync_empty_body_returns_422(): void
    {
        $response = $this->postJson($this->syncUrl(), [], $this->authHeaders($this->farmer));

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    /** client_offline_since tidak dikirim → 422 */
    public function test_sync_missing_offline_since_returns_422(): void
    {
        $payload = [
            'client_sync_id' => 'sync-no-since-001',
            'batch_photos' => [],
        ];

        $response = $this->postJson($this->syncUrl(), $payload, $this->authHeaders($this->farmer));

        $response->assertStatus(422);
    }

    /** client_sync_id tidak dikirim → 422 */
    public function test_sync_missing_client_sync_id_returns_422(): void
    {
        $payload = [
            'client_offline_since' => '2026-06-03T08:00:00Z',
            'batch_photos' => [],
        ];

        $response = $this->postJson($this->syncUrl(), $payload, $this->authHeaders($this->farmer));

        $response->assertStatus(422);
    }

    /** client_offline_since format bukan ISO 8601 → 422 */
    public function test_sync_invalid_datetime_format_returns_422(): void
    {
        $payload = [
            'client_offline_since' => 'tanggal-sekarang',
            'client_sync_id' => 'sync-bad-date-001',
            'batch_photos' => [],
        ];

        $response = $this->postJson($this->syncUrl(), $payload, $this->authHeaders($this->farmer));

        $response->assertStatus(422);
    }

    /** client_offline_since tanggal di masa depan (2027) → 422 */
    public function test_sync_future_offline_since_returns_422(): void
    {
        $payload = [
            'client_offline_since' => '2027-12-31T00:00:00Z',
            'client_sync_id' => 'sync-future-001',
            'batch_photos' => [],
        ];

        $response = $this->postJson($this->syncUrl(), $payload, $this->authHeaders($this->farmer));

        $response->assertStatus(422);
    }

    /** client_sync_id bukan string (integer) → 422 */
    public function test_sync_non_string_client_sync_id_returns_422(): void
    {
        $payload = [
            'client_offline_since' => '2026-06-03T08:00:00Z',
            'client_sync_id' => 12345,
            'batch_photos' => [],
        ];

        $response = $this->postJson($this->syncUrl(), $payload, $this->authHeaders($this->farmer));

        $response->assertStatus(422);
    }

    /** client_sync_id string kosong → 422 */
    public function test_sync_empty_client_sync_id_returns_422(): void
    {
        $payload = [
            'client_offline_since' => '2026-06-03T08:00:00Z',
            'client_sync_id' => '',
            'batch_photos' => [],
        ];

        $response = $this->postJson($this->syncUrl(), $payload, $this->authHeaders($this->farmer));

        $response->assertStatus(422);
    }

    /** batch_photos bukan array (string) → 422 */
    public function test_sync_invalid_batch_photos_type_returns_422(): void
    {
        $payload = [
            'client_offline_since' => '2026-06-03T08:00:00Z',
            'client_sync_id' => 'sync-bad-type-001',
            'batch_photos' => 'bukan-array',
        ];

        $response = $this->postJson($this->syncUrl(), $payload, $this->authHeaders($this->farmer));

        $response->assertStatus(422);
    }

    /** Validasi item: batch_id kosong dalam array batch_photos → 422 */
    public function test_sync_photo_missing_batch_id_returns_422(): void
    {
        $payload = [
            'client_offline_since' => '2026-06-03T08:00:00Z',
            'client_sync_id' => 'sync-no-batch-001',
            'batch_photos' => [
                [
                    'client_temp_id' => 'temp-photo-no-batch',
                    'filename' => 'photo.jpg',
                    'photo_data_base64' => $this->validBase64Jpeg,
                    'note' => 'Tanpa batch_id',
                    'created_at_local' => '2026-06-03T09:00:00Z',
                ],
            ],
        ];

        $response = $this->postJson($this->syncUrl(), $payload, $this->authHeaders($this->farmer));

        $response->assertStatus(422);
    }

    /** Validasi item: photo_data_base64 kosong → 422 */
    public function test_sync_photo_empty_base64_returns_422(): void
    {
        $batch = $this->createBatchForFarmer($this->farmer);

        $payload = [
            'client_offline_since' => '2026-06-03T08:00:00Z',
            'client_sync_id' => 'sync-empty-b64-001',
            'batch_photos' => [
                [
                    'client_temp_id' => 'temp-photo-empty-b64',
                    'batch_id' => $batch->id,
                    'filename' => 'photo.jpg',
                    'photo_data_base64' => '',
                    'note' => 'Base64 kosong',
                    'created_at_local' => '2026-06-03T09:00:00Z',
                ],
            ],
        ];

        $response = $this->postJson($this->syncUrl(), $payload, $this->authHeaders($this->farmer));

        $response->assertStatus(422);
    }

    /** Validasi item: filename kosong → 422 */
    public function test_sync_photo_missing_filename_returns_422(): void
    {
        $batch = $this->createBatchForFarmer($this->farmer);

        $payload = [
            'client_offline_since' => '2026-06-03T08:00:00Z',
            'client_sync_id' => 'sync-no-filename-001',
            'batch_photos' => [
                [
                    'client_temp_id' => 'temp-photo-no-file',
                    'batch_id' => $batch->id,
                    'filename' => '',
                    'photo_data_base64' => $this->validBase64Jpeg,
                    'note' => 'Tanpa filename',
                    'created_at_local' => '2026-06-03T09:00:00Z',
                ],
            ],
        ];

        $response = $this->postJson($this->syncUrl(), $payload, $this->authHeaders($this->farmer));

        $response->assertStatus(422);
    }

    /** Validasi item: photo_data_base64 bukan format valid (random string) → partial failure 200 */
    public function test_sync_photo_invalid_base64_format_partial_failure(): void
    {
        $batch = $this->createBatchForFarmer($this->farmer);

        $payload = [
            'client_offline_since' => '2026-06-03T08:00:00Z',
            'client_sync_id' => 'sync-invalid-b64-001',
            'batch_photos' => [
                [
                    'client_temp_id' => 'temp-photo-bad-b64',
                    'batch_id' => $batch->id,
                    'filename' => 'corrupted.jpg',
                    'photo_data_base64' => 'ini-bukan-base64',
                    'note' => 'Format base64 rusak',
                    'created_at_local' => '2026-06-03T09:00:00Z',
                ],
            ],
        ];

        $response = $this->postJson($this->syncUrl(), $payload, $this->authHeaders($this->farmer));

        // Invalid base64 → partial failure (200 + failed_count > 0)
        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('data.results.photos.failed_count'));

        $item = $response->json('data.results.photos.items.0');
        $this->assertEquals('failed', $item['status']);
    }

    /** Validasi item: note tidak wajib — tanpa note tetap sukses */
    public function test_sync_photo_without_note_succeeds(): void
    {
        $batch = $this->createBatchForFarmer($this->farmer);

        $payload = [
            'client_offline_since' => '2026-06-03T08:00:00Z',
            'client_sync_id' => 'sync-no-note-001',
            'batch_photos' => [
                [
                    'client_temp_id' => 'temp-photo-no-note',
                    'batch_id' => $batch->id,
                    'filename' => 'photo-no-note.jpg',
                    'photo_data_base64' => $this->validBase64Jpeg,
                    'created_at_local' => '2026-06-03T09:00:00Z',
                ],
            ],
        ];

        $response = $this->postJson($this->syncUrl(), $payload, $this->authHeaders($this->farmer));

        $response->assertStatus(200)
            ->assertJsonPath('data.results.photos.success_count', 1)
            ->assertJsonPath('data.results.photos.failed_count', 0);
    }

    // =========================================================================
    // 4. IDEMPOTENSI — client_sync_id
    // =========================================================================

    /** Kirim sync 2x dengan client_sync_id sama → response idempotent (data tidak duplikat) */
    public function test_sync_idempotent_same_client_sync_id(): void
    {
        $payload = $this->validSyncPayload('sync-idempotent-001');

        // First sync
        $response1 = $this->postJson($this->syncUrl(), $payload, $this->authHeaders($this->farmer));
        $response1->assertStatus(200);

        // Count photos after first sync
        $photoCount1 = BatchPhoto::count();

        // Second sync with same client_sync_id
        $response2 = $this->postJson($this->syncUrl(), $payload, $this->authHeaders($this->farmer));
        $response2->assertStatus(200);

        // Photo count should not increase
        $photoCount2 = BatchPhoto::count();
        $this->assertEquals($photoCount1, $photoCount2);

        // Response should contain same sync_id from first call
        $this->assertEquals(
            $response1->json('data.sync_id'),
            $response2->json('data.sync_id')
        );
    }

    /** Kirim sync dengan client_sync_id berbeda → data baru terbuat */
    public function test_sync_different_client_sync_id_creates_new_data(): void
    {
        $payload1 = $this->validSyncPayload('sync-diff-001');
        $payload2 = $this->validSyncPayload('sync-diff-002');

        $response1 = $this->postJson($this->syncUrl(), $payload1, $this->authHeaders($this->farmer));
        $response1->assertStatus(200);

        $response2 = $this->postJson($this->syncUrl(), $payload2, $this->authHeaders($this->farmer));
        $response2->assertStatus(200);

        // sync_id harus berbeda
        $this->assertNotEquals(
            $response1->json('data.sync_id'),
            $response2->json('data.sync_id')
        );
    }

    /** Client_sync_id bersifat per-user: farmer berbeda boleh pakai ID sama */
    public function test_sync_same_client_sync_id_different_farmers(): void
    {
        $batch1 = $this->createBatchForFarmer($this->farmer);
        $batch2 = $this->createBatchForFarmer($this->farmer2);

        $payloadFarmer1 = [
            'client_offline_since' => '2026-06-03T08:00:00Z',
            'client_sync_id' => 'sync-same-uuid-shared',
            'batch_photos' => [
                [
                    'client_temp_id' => 'temp-f1-photo',
                    'batch_id' => $batch1->id,
                    'filename' => 'farmer1-photo.jpg',
                    'photo_data_base64' => $this->validBase64Jpeg,
                    'note' => 'Foto farmer 1',
                    'created_at_local' => '2026-06-03T09:00:00Z',
                ],
            ],
        ];

        $payloadFarmer2 = [
            'client_offline_since' => '2026-06-03T08:00:00Z',
            'client_sync_id' => 'sync-same-uuid-shared',
            'batch_photos' => [
                [
                    'client_temp_id' => 'temp-f2-photo',
                    'batch_id' => $batch2->id,
                    'filename' => 'farmer2-photo.jpg',
                    'photo_data_base64' => $this->validBase64Jpeg,
                    'note' => 'Foto farmer 2',
                    'created_at_local' => '2026-06-03T09:00:00Z',
                ],
            ],
        ];

        $response1 = $this->postJson($this->syncUrl(), $payloadFarmer1, $this->authHeaders($this->farmer));
        $response1->assertStatus(200);

        $response2 = $this->postJson($this->syncUrl(), $payloadFarmer2, $this->authHeaders($this->farmer2));
        $response2->assertStatus(200);

        // Kedua sync harus punya sync_id berbeda
        $this->assertNotEquals(
            $response1->json('data.sync_id'),
            $response2->json('data.sync_id')
        );

        // Total foto = 2 (tidak ada duplikasi yang dicegah lintas user)
        $this->assertEquals(2, BatchPhoto::count());
    }

    // =========================================================================
    // 5. PARTIAL FAILURE — HTTP 200 + failed_count > 0
    // =========================================================================

    /** Mix: 1 foto valid + 1 foto ke batch yang tidak ada → partial failure */
    public function test_sync_mixed_results_valid_and_invalid_batch(): void
    {
        $validBatch = $this->createBatchForFarmer($this->farmer);

        $payload = [
            'client_offline_since' => '2026-06-03T08:00:00Z',
            'client_sync_id' => 'sync-mixed-001',
            'batch_photos' => [
                [
                    'client_temp_id' => 'temp-photo-valid',
                    'batch_id' => $validBatch->id,
                    'filename' => 'valid-photo.jpg',
                    'photo_data_base64' => $this->validBase64Jpeg,
                    'note' => 'Foto valid',
                    'created_at_local' => '2026-06-03T09:00:00Z',
                ],
                [
                    'client_temp_id' => 'temp-photo-ghost',
                    'batch_id' => 'batch-nonexistent-999',
                    'filename' => 'ghost-photo.jpg',
                    'photo_data_base64' => $this->validBase64Jpeg,
                    'note' => 'Foto ke batch tidak ada',
                    'created_at_local' => '2026-06-03T09:30:00Z',
                ],
            ],
        ];

        $response = $this->postJson($this->syncUrl(), $payload, $this->authHeaders($this->farmer));

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('data.results.photos.total_sent'));
        $this->assertEquals(1, $response->json('data.results.photos.success_count'));
        $this->assertEquals(1, $response->json('data.results.photos.failed_count'));

        // Identifikasi item mana yang gagal
        $items = $response->json('data.results.photos.items');
        $failedItem = collect($items)->first(fn ($i) => $i['status'] === 'failed');
        $this->assertNotNull($failedItem);
        $this->assertEquals('temp-photo-ghost', $failedItem['client_temp_id']);
        $this->assertArrayHasKey('error', $failedItem);
    }

    /** Mix: beberapa foto gagal karena batch milik farmer lain */
    public function test_sync_mixed_failure_batch_not_owned(): void
    {
        $myBatch = $this->createBatchForFarmer($this->farmer);
        $otherBatch = $this->createBatchForFarmer($this->farmer2);

        $payload = [
            'client_offline_since' => '2026-06-03T08:00:00Z',
            'client_sync_id' => 'sync-not-owned-001',
            'batch_photos' => [
                [
                    'client_temp_id' => 'temp-mine-1',
                    'batch_id' => $myBatch->id,
                    'filename' => 'my-photo.jpg',
                    'photo_data_base64' => $this->validBase64Jpeg,
                    'note' => 'Foto milik saya',
                    'created_at_local' => '2026-06-03T09:00:00Z',
                ],
                [
                    'client_temp_id' => 'temp-theirs-1',
                    'batch_id' => $otherBatch->id,
                    'filename' => 'their-photo.jpg',
                    'photo_data_base64' => $this->validBase64Jpeg,
                    'note' => 'Foto milik orang lain',
                    'created_at_local' => '2026-06-03T09:30:00Z',
                ],
            ],
        ];

        $response = $this->postJson($this->syncUrl(), $payload, $this->authHeaders($this->farmer));

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('data.results.photos.success_count'));
        $this->assertEquals(1, $response->json('data.results.photos.failed_count'));
    }

    /** Semua foto gagal → tetap 200 dengan failed_count = total_sent */
    public function test_sync_all_items_fail_returns_200_with_failed_count(): void
    {
        $payload = [
            'client_offline_since' => '2026-06-03T08:00:00Z',
            'client_sync_id' => 'sync-all-fail-001',
            'batch_photos' => [
                [
                    'client_temp_id' => 'temp-fail-1',
                    'batch_id' => 'batch-deleted-001',
                    'filename' => 'fail1.jpg',
                    'photo_data_base64' => $this->validBase64Jpeg,
                    'note' => 'Batch tidak ada',
                    'created_at_local' => '2026-06-03T09:00:00Z',
                ],
                [
                    'client_temp_id' => 'temp-fail-2',
                    'batch_id' => 'batch-deleted-002',
                    'filename' => 'fail2.jpg',
                    'photo_data_base64' => $this->validBase64Jpeg,
                    'note' => 'Batch juga tidak ada',
                    'created_at_local' => '2026-06-03T09:30:00Z',
                ],
            ],
        ];

        $response = $this->postJson($this->syncUrl(), $payload, $this->authHeaders($this->farmer));

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('data.results.photos.total_sent'));
        $this->assertEquals(0, $response->json('data.results.photos.success_count'));
        $this->assertEquals(2, $response->json('data.results.photos.failed_count'));
    }

    // =========================================================================
    // 6. LOGIKA BISNIS — OFFLINE_SYNC_CONFLICT
    // =========================================================================

    /** Sync foto ke batch yang sudah dihapus → OFFLINE_SYNC_CONFLICT (409) */
    public function test_sync_to_deleted_batch_returns_409_conflict(): void
    {
        // Buat batch, lalu hapus (soft delete)
        $batch = $this->createBatchForFarmer($this->farmer, 'draft');
        $batchId = $batch->id;
        $batch->delete();

        $payload = [
            'client_offline_since' => '2026-06-03T08:00:00Z',
            'client_sync_id' => 'sync-deleted-batch-001',
            'batch_photos' => [
                [
                    'client_temp_id' => 'temp-photo-deleted-batch',
                    'batch_id' => $batchId,
                    'filename' => 'too-late.jpg',
                    'photo_data_base64' => $this->validBase64Jpeg,
                    'note' => 'Batch sudah dihapus saat offline',
                    'created_at_local' => '2026-06-03T09:00:00Z',
                ],
            ],
        ];

        $response = $this->postJson($this->syncUrl(), $payload, $this->authHeaders($this->farmer));

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'code' => 'OFFLINE_SYNC_CONFLICT',
            ]);
    }

    /** Sync ke batch acquired — status batch sudah tidak aktif, foto seharusnya ditolak */
    public function test_sync_to_acquired_batch_partial_failure(): void
    {
        $batch = $this->createBatchForFarmer($this->farmer, 'acquired');

        $payload = [
            'client_offline_since' => '2026-06-03T08:00:00Z',
            'client_sync_id' => 'sync-acquired-001',
            'batch_photos' => [
                [
                    'client_temp_id' => 'temp-photo-acquired',
                    'batch_id' => $batch->id,
                    'filename' => 'photo-acquired.jpg',
                    'photo_data_base64' => $this->validBase64Jpeg,
                    'note' => 'Upload ke batch acquired',
                    'created_at_local' => '2026-06-03T09:00:00Z',
                ],
            ],
        ];

        $response = $this->postJson($this->syncUrl(), $payload, $this->authHeaders($this->farmer));

        // Batch acquired tidak menerima foto baru
        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('data.results.photos.failed_count'));
    }

    /** Sync foto ke batch dengan status processing — batch sedang diproses, foto mungkin ditolak */
    public function test_sync_to_processing_batch_partial_failure(): void
    {
        $batch = $this->createBatchForFarmer($this->farmer, 'processing');

        $payload = [
            'client_offline_since' => '2026-06-03T08:00:00Z',
            'client_sync_id' => 'sync-processing-001',
            'batch_photos' => [
                [
                    'client_temp_id' => 'temp-photo-processing',
                    'batch_id' => $batch->id,
                    'filename' => 'photo-processing.jpg',
                    'photo_data_base64' => $this->validBase64Jpeg,
                    'note' => 'Upload ke batch processing',
                    'created_at_local' => '2026-06-03T09:00:00Z',
                ],
            ],
        ];

        $response = $this->postJson($this->syncUrl(), $payload, $this->authHeaders($this->farmer));

        // Processing batch seharusnya menolak foto offline
        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('data.results.photos.failed_count'));
    }

    // =========================================================================
    // 7. V2.1 SPECIFIC — batch_logs DIHAPUS
    // =========================================================================

    /** Field batch_logs TIDAK boleh dikirim — jika ada, harus diabaikan atau 422 */
    public function test_sync_with_batch_logs_field_is_rejected_or_ignored(): void
    {
        $batch = $this->createBatchForFarmer($this->farmer);

        $payload = [
            'client_offline_since' => '2026-06-03T08:00:00Z',
            'client_sync_id' => 'sync-has-logs-001',
            'batch_photos' => [
                [
                    'client_temp_id' => 'temp-photo-with-logs',
                    'batch_id' => $batch->id,
                    'filename' => 'photo.jpg',
                    'photo_data_base64' => $this->validBase64Jpeg,
                    'note' => 'Foto saja',
                    'created_at_local' => '2026-06-03T09:00:00Z',
                ],
            ],
            // batch_logs field — DIHAPUS di V2.1
            'batch_logs' => [
                [
                    'client_temp_id' => 'temp-log-001',
                    'batch_id' => $batch->id,
                    'log_type' => 'monitoring',
                    'note' => 'Log tidak boleh ada di V2.1',
                    'created_at_local' => '2026-06-03T09:00:00Z',
                ],
            ],
        ];

        $response = $this->postJson($this->syncUrl(), $payload, $this->authHeaders($this->farmer));

        // batch_logs harus diabaikan — hanya photos yang diproses
        $response->assertStatus(200)
            ->assertJsonPath('data.results.photos.success_count', 1);

        // Response tidak boleh punya key 'logs' di results
        $results = $response->json('data.results');
        $this->assertArrayNotHasKey('logs', $results);
    }

    /** Response hanya punya results.photos — tidak ada results.logs */
    public function test_sync_response_has_only_photos_in_results(): void
    {
        $payload = $this->validSyncPayload('sync-photos-only-001');

        $response = $this->postJson($this->syncUrl(), $payload, $this->authHeaders($this->farmer));

        $response->assertStatus(200);

        $results = $response->json('data.results');
        $this->assertArrayHasKey('photos', $results);
        // Pastikan tidak ada key lain selain photos di results
        $this->assertEquals(['photos'], array_keys($results));
    }

    // =========================================================================
    // 8. BOUNDARY & STRESS
    // =========================================================================

    /** Sync dengan banyak foto sekaligus (stress test — 10 foto dalam 1 request) */
    public function test_sync_bulk_photos_10_items(): void
    {
        $batch = $this->createBatchForFarmer($this->farmer);

        $photos = [];
        for ($i = 0; $i < 10; $i++) {
            $photos[] = [
                'client_temp_id' => 'temp-bulk-'.str_pad($i, 3, '0', STR_PAD_LEFT),
                'batch_id' => $batch->id,
                'filename' => "bulk-photo-{$i}.jpg",
                'photo_data_base64' => $this->validBase64Jpeg,
                'note' => 'Foto ke-'.($i + 1).' dari offline',
                'created_at_local' => "2026-06-03T0{$i}:00:00Z",
            ];
        }

        $payload = [
            'client_offline_since' => '2026-06-03T08:00:00Z',
            'client_sync_id' => 'sync-bulk-10-001',
            'batch_photos' => $photos,
        ];

        $response = $this->postJson($this->syncUrl(), $payload, $this->authHeaders($this->farmer));

        $response->assertStatus(200)
            ->assertJsonPath('data.results.photos.total_sent', 10);
    }

    /** Sync dengan base64 berukuran besar — foto resolusi tinggi */
    public function test_sync_large_base64_photo(): void
    {
        $batch = $this->createBatchForFarmer($this->farmer);

        // Buat base64 besar (~500KB data + prefix)
        $largeData = str_repeat('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAIBAQIB', 5000);
        $largeBase64 = 'data:image/jpeg;base64,'.$largeData;

        $payload = [
            'client_offline_since' => '2026-06-03T08:00:00Z',
            'client_sync_id' => 'sync-large-photo-001',
            'batch_photos' => [
                [
                    'client_temp_id' => 'temp-photo-large',
                    'batch_id' => $batch->id,
                    'filename' => 'large-photo.jpg',
                    'photo_data_base64' => $largeBase64,
                    'note' => 'Foto resolusi tinggi',
                    'created_at_local' => '2026-06-03T09:00:00Z',
                ],
            ],
        ];

        $response = $this->postJson($this->syncUrl(), $payload, $this->authHeaders($this->farmer));

        // Harusnya sukses (asumsi server bisa handle) atau partial failure jika terlalu besar
        $response->assertStatus(200);
    }

    /** client_offline_since 1 bulan yang lalu — durasi offline panjang tetap diterima */
    public function test_sync_long_offline_duration_accepted(): void
    {
        $payload = [
            'client_offline_since' => '2026-05-03T08:00:00Z', // 1 bulan lalu
            'client_sync_id' => 'sync-long-offline-001',
            'batch_photos' => [],
        ];

        $response = $this->postJson($this->syncUrl(), $payload, $this->authHeaders($this->farmer));

        $response->assertStatus(200);
    }

    /** Timestamp response konsisten (synced_at >= client_offline_since) */
    public function test_sync_timestamp_consistency(): void
    {
        $offlineSince = '2026-06-03T08:00:00Z';
        $payload = $this->validSyncPayload('sync-timestamp-001');
        $payload['client_offline_since'] = $offlineSince;

        $response = $this->postJson($this->syncUrl(), $payload, $this->authHeaders($this->farmer));

        $response->assertStatus(200);

        $syncedAt = $response->json('data.synced_at');
        // synced_at harus >= client_offline_since
        $this->assertGreaterThanOrEqual(
            strtotime($offlineSince),
            strtotime($syncedAt)
        );
    }

    // =========================================================================
    // 9. FORMAT FILE GAMBAR
    // =========================================================================

    /** Sync foto PNG via base64 — sukses */
    public function test_sync_png_photo_success(): void
    {
        $batch = $this->createBatchForFarmer($this->farmer);

        $payload = [
            'client_offline_since' => '2026-06-03T08:00:00Z',
            'client_sync_id' => 'sync-png-001',
            'batch_photos' => [
                [
                    'client_temp_id' => 'temp-photo-png',
                    'batch_id' => $batch->id,
                    'filename' => 'offline-photo.png',
                    'photo_data_base64' => $this->validBase64Png,
                    'note' => 'Foto format PNG',
                    'created_at_local' => '2026-06-03T09:00:00Z',
                ],
            ],
        ];

        $response = $this->postJson($this->syncUrl(), $payload, $this->authHeaders($this->farmer));

        $response->assertStatus(200)
            ->assertJsonPath('data.results.photos.success_count', 1);
    }

    /** Sync foto dengan base64 tanpa data prefix — harus gagal atau ditolak */
    public function test_sync_base64_without_mime_prefix_partial_failure(): void
    {
        $batch = $this->createBatchForFarmer($this->farmer);

        // Base64 tanpa "data:image/jpeg;base64," prefix
        $rawBase64 = '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAMCAgICAgMCAgIDAwMDBAYEBAQEBAgGBgUGCQgKCgkICQkKDA8MCgsOCwkJDRENDg8QEBEQCgwSFBIQMQwSBQ0P';

        $payload = [
            'client_offline_since' => '2026-06-03T08:00:00Z',
            'client_sync_id' => 'sync-raw-b64-001',
            'batch_photos' => [
                [
                    'client_temp_id' => 'temp-photo-raw',
                    'batch_id' => $batch->id,
                    'filename' => 'raw-base64.jpg',
                    'photo_data_base64' => $rawBase64,
                    'note' => 'Tanpa MIME prefix',
                    'created_at_local' => '2026-06-03T09:00:00Z',
                ],
            ],
        ];

        $response = $this->postJson($this->syncUrl(), $payload, $this->authHeaders($this->farmer));

        // Tanpa prefix → partial failure atau 422 tergantung implementasi
        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('data.results.photos.failed_count'));
    }

    // =========================================================================
    // 10. PROFIL PETANI — PHONE_VERIFIED & PROFILE_INCOMPLETE
    // =========================================================================

    /** Sync oleh farmer yang phone belum diverifikasi → 403 PHONE_NOT_VERIFIED */
    public function test_sync_phone_not_verified_returns_403(): void
    {
        $unverifiedFarmer = User::factory()->create([
            'role' => 'farmer',
            'phone' => '+62 814-0000-0000',
            'phone_verified' => false,
        ]);

        $payload = $this->validSyncPayload('sync-unverified-001');

        // Override batch ke farmer yang tidak verified
        $batch = $this->createBatchForFarmer($unverifiedFarmer);
        $payload['batch_photos'][0]['batch_id'] = $batch->id;

        $response = $this->postJson($this->syncUrl(), $payload, $this->authHeaders($unverifiedFarmer));

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'PHONE_NOT_VERIFIED',
            ]);
    }

    /** Sync oleh farmer dengan profil belum lengkap → 403 PROFILE_INCOMPLETE */
    public function test_sync_profile_incomplete_returns_403(): void
    {
        $incompleteFarmer = User::factory()->create([
            'role' => 'farmer',
            'phone' => '+62 815-0000-0000',
            'phone_verified' => true,
            'location' => null,
            'profile_completion' => 40,
        ]);

        $batch = $this->createBatchForFarmer($incompleteFarmer);
        $payload = [
            'client_offline_since' => '2026-06-03T08:00:00Z',
            'client_sync_id' => 'sync-incomplete-001',
            'batch_photos' => [
                [
                    'client_temp_id' => 'temp-photo-incomplete',
                    'batch_id' => $batch->id,
                    'filename' => 'photo.jpg',
                    'photo_data_base64' => $this->validBase64Jpeg,
                    'note' => 'Profil belum lengkap',
                    'created_at_local' => '2026-06-03T09:00:00Z',
                ],
            ],
        ];

        $response = $this->postJson($this->syncUrl(), $payload, $this->authHeaders($incompleteFarmer));

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'PROFILE_INCOMPLETE',
            ]);
    }
}

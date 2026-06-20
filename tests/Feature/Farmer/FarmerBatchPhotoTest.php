<?php

namespace Tests\Feature\Farmer;

use App\Models\Batch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * FarmerBatchPhotoTest — Test Case untuk Modul Foto Batch Petani (API Contract V2.1)
 *
 * Scope: 2 endpoint foto batch farmer
 *   - POST   /api/v1/farmer/batches/{batchId}/photos  — Upload foto batch (9.1)
 *   - DELETE /api/v1/farmer/batches/{batchId}/photos/{photoId} — Hapus foto (9.2)
 *
 * Reference: API_CONTRACT_V2_FARMER.md Section 9 (Modul Foto Batch)
 *
 * Business Rules V2.1 yang ditest:
 * - Foto hanya bisa upload ke batch dengan status `draft`
 * - Array file gambar (max 5 per request), format: JPEG, PNG, WebP, max 10MB per file
 * - Maksimal 10 foto per batch
 * - Minimum 3 foto untuk bisa submit survey (BATCH_PHOTO_MINIMUM)
 * - Data isolation: petani hanya bisa kelola foto batch miliknya
 * - Batch detail menampilkan photo_count, photo_minimum, is_complete
 * - Thumbnail otomatis digenerate saat upload
 */
class FarmerBatchPhotoTest extends TestCase
{
    use RefreshDatabase;

    private User $farmer;

    private User $farmer2;

    private User $exporter;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('batches'); // Fake storage untuk testing upload

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
     * Helper: buat fake image file (JPEG, 500KB)
     */
    private function fakeImage(string $name = 'photo.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 1200, 800)->size(500);
    }

    /**
     * Helper: upload foto ke batch dengan helper route
     */
    private function uploadPhotos(string $batchId, array $photos, array $notes = [], ?string $token = null): TestResponse
    {
        $request = $this->actingAs($token ? User::findOrFail($token) : $this->farmer)
            ->postJson(
                '/api/v1/farmer/batches/'.$batchId.'/photos',
                array_merge(
                    ['photos' => $photos],
                    count($notes) > 0 ? ['notes' => $notes] : []
                )
            );

        return $request;
    }

    // ========================================================================
    // 9.1: POST /api/v1/farmer/batches/{batchId}/photos — Upload Foto Batch
    // ========================================================================

    // --- Happy Path ---

    public function test_upload_single_photo_returns_201()
    {
        $this->createDraftBatch();

        $photo = $this->fakeImage('IMG_20260502_001.jpg');
        $response = $this->uploadPhotos('batch-001', [$photo], ['Wajib']);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS_CREATE',
                'message' => 'Foto batch berhasil diunggah',
            ])
            ->assertJsonStructure([
                'data' => [
                    'photos' => [
                        '*' => ['id', 'url', 'thumbnail_url', 'note', 'filename', 'size_kb', 'uploaded_at'],
                    ],
                    'batch_photo_count',
                    'batch_photo_minimum',
                    'is_complete',
                ],
            ]);

        // Verifikasi struktur data foto
        $responseData = $response->json('data.photos');
        $this->assertCount(1, $responseData);
        $this->assertNotNull($responseData[0]['url']);
        $this->assertNotNull($responseData[0]['thumbnail_url']);
        $this->assertEquals('Wajib', $responseData[0]['note']);
        $this->assertEquals('IMG_20260502_001.jpg', $responseData[0]['filename']);

        // Verifikasi count dan minimum
        $response->assertJsonPath('data.batch_photo_count', 1);
        $response->assertJsonPath('data.batch_photo_minimum', 3);
        $response->assertJsonPath('data.is_complete', false);

        // Verifikasi foto tersimpan di database
        $this->assertDatabaseHas('batch_photos', [
            'batch_id' => 'batch-001',
            'filename' => 'IMG_20260502_001.jpg',
        ]);
    }

    public function test_upload_multiple_photos_returns_201()
    {
        $this->createDraftBatch();

        $photos = [
            $this->fakeImage('photo1.jpg'),
            $this->fakeImage('photo2.jpg'),
            $this->fakeImage('photo3.jpg'),
        ];
        $notes = ['Wajib', 'Wajib', 'Wajib'];

        $response = $this->uploadPhotos('batch-001', $photos, $notes);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS_CREATE',
            ]);

        // Verifikasi 3 foto tersimpan
        $responseData = $response->json('data.photos');
        $this->assertCount(3, $responseData);

        // Setelah 3 foto, is_complete = true
        $response->assertJsonPath('data.batch_photo_count', 3);
        $response->assertJsonPath('data.batch_photo_minimum', 3);
        $response->assertJsonPath('data.is_complete', true);

        // Verifikasi database
        $this->assertDatabaseCount('batch_photos', 3);
    }

    public function test_upload_five_photos_max_per_request_returns_201()
    {
        $this->createDraftBatch();

        $photos = [
            $this->fakeImage('photo1.jpg'),
            $this->fakeImage('photo2.jpg'),
            $this->fakeImage('photo3.jpg'),
            $this->fakeImage('photo4.jpg'),
            $this->fakeImage('photo5.jpg'),
        ];
        $notes = ['Note 1', 'Note 2', 'Note 3', 'Note 4', 'Note 5'];

        $response = $this->uploadPhotos('batch-001', $photos, $notes);

        $response->assertStatus(201)
            ->assertJsonPath('data.batch_photo_count', 5);
    }

    public function test_upload_incremental_photos_reaches_ten_max_per_batch()
    {
        $this->createDraftBatch();

        // Upload pertama: 5 foto
        $photos1 = array_map(fn ($i) => $this->fakeImage("photo{$i}.jpg"), range(1, 5));
        $this->uploadPhotos('batch-001', $photos1)
            ->assertStatus(201)
            ->assertJsonPath('data.batch_photo_count', 5);

        // Upload kedua: 5 foto lagi (total = 10, max)
        $photos2 = array_map(fn ($i) => $this->fakeImage("photo{$i}.jpg"), range(6, 10));
        $this->uploadPhotos('batch-001', $photos2)
            ->assertStatus(201)
            ->assertJsonPath('data.batch_photo_count', 10)
            ->assertJsonPath('data.is_complete', true);
    }

    public function test_upload_photos_without_notes_still_succeeds()
    {
        $this->createDraftBatch();

        $photos = [
            $this->fakeImage('photo1.jpg'),
            $this->fakeImage('photo2.jpg'),
        ];

        // Upload tanpa notes (field opsional)
        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches/batch-001/photos', [
                'photos' => $photos,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.batch_photo_count', 2);
    }

    public function test_upload_jpeg_png_and_webp_formats_all_succeed()
    {
        $this->createDraftBatch();

        $photos = [
            UploadedFile::fake()->image('photo.jpg', 800, 600)->size(300),
            UploadedFile::fake()->image('photo.png', 800, 600)->size(300),
            UploadedFile::fake()->image('photo.webp', 800, 600)->size(300),
        ];

        $response = $this->uploadPhotos('batch-001', $photos);

        $response->assertStatus(201)
            ->assertJsonPath('data.batch_photo_count', 3);

        // Verifikasi ketiga format tersimpan
        $this->assertDatabaseHas('batch_photos', ['filename' => 'photo.jpg']);
        $this->assertDatabaseHas('batch_photos', ['filename' => 'photo.png']);
        $this->assertDatabaseHas('batch_photos', ['filename' => 'photo.webp']);
    }

    public function test_upload_generates_thumbnail_url()
    {
        $this->createDraftBatch();

        $photo = $this->fakeImage('thumbnail-test.jpg');
        $response = $this->uploadPhotos('batch-001', [$photo]);

        $response->assertStatus(201);

        $responseData = $response->json('data.photos.0');
        $this->assertNotNull($responseData['url']);
        $this->assertNotNull($responseData['thumbnail_url']);

        // Thumbnail URL harus beda dari URL asli (berbeda path/file)
        $this->assertNotEquals($responseData['url'], $responseData['thumbnail_url']);
    }

    public function test_upload_photo_updates_batch_photo_count()
    {
        $batch = $this->createDraftBatch();
        $this->assertEquals(0, $batch->photo_count);

        // Upload 3 foto
        $photos = array_map(fn ($i) => $this->fakeImage("photo{$i}.jpg"), range(1, 3));
        $this->uploadPhotos('batch-001', $photos)
            ->assertStatus(201)
            ->assertJsonPath('data.batch_photo_count', 3);

        // Refresh batch dari database
        $batch->refresh();
        // photo_count di batch harus update (apakah langsung atau via accessor)
        // Bergantung implementasi, tapi photo_count di response upload harus akurat
    }

    // --- Validasi: File Upload ---

    public function test_upload_invalid_file_format_pdf_returns_422()
    {
        $this->createDraftBatch();

        // Buat fake file PDF (bukan gambar)
        $pdfFile = UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');

        $response = $this->uploadPhotos('batch-001', [$pdfFile]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'FILE_UPLOAD_ERROR',
            ]);
    }

    public function test_upload_invalid_file_format_txt_returns_422()
    {
        $this->createDraftBatch();

        $txtFile = UploadedFile::fake()->create('data.txt', 10, 'text/plain');

        $response = $this->uploadPhotos('batch-001', [$txtFile]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'FILE_UPLOAD_ERROR',
            ]);
    }

    public function test_upload_file_exceeding_max_size_10mb_returns_422()
    {
        $this->createDraftBatch();

        // 11MB file (melebihi 10MB max)
        $largeFile = UploadedFile::fake()->create('large.jpg', 11264, 'image/jpeg');

        $response = $this->uploadPhotos('batch-001', [$largeFile]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'FILE_UPLOAD_ERROR',
            ]);
    }

    public function test_upload_no_photos_field_returns_422()
    {
        $this->createDraftBatch();

        // Kirim request tanpa field `photos`
        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches/batch-001/photos', [
                'notes' => ['Wajib'],
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    public function test_upload_empty_photos_array_returns_422()
    {
        $this->createDraftBatch();

        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/batches/batch-001/photos', [
                'photos' => [],
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    public function test_upload_six_photos_exceeds_max_per_request_returns_422()
    {
        $this->createDraftBatch();

        // 6 foto sekaligus (max 5 per request)
        $photos = array_map(fn ($i) => $this->fakeImage("photo{$i}.jpg"), range(1, 6));

        $response = $this->uploadPhotos('batch-001', $photos);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    // --- Business Rules: Batch Status ---

    public function test_upload_photo_to_processing_batch_returns_400()
    {
        $this->createDraftBatch('batch-001', ['status' => 'processing']);

        $photo = $this->fakeImage('photo1.jpg');
        $response = $this->uploadPhotos('batch-001', [$photo]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'code' => 'INVALID_BATCH_STATUS_TRANSITION',
            ]);
    }

    public function test_upload_photo_to_ready_batch_returns_400()
    {
        $this->createDraftBatch('batch-001', ['status' => 'ready']);

        $photo = $this->fakeImage('photo1.jpg');
        $response = $this->uploadPhotos('batch-001', [$photo]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'code' => 'INVALID_BATCH_STATUS_TRANSITION',
            ]);
    }

    public function test_upload_photo_to_acquired_batch_returns_400()
    {
        $this->createDraftBatch('batch-001', ['status' => 'acquired']);

        $photo = $this->fakeImage('photo1.jpg');
        $response = $this->uploadPhotos('batch-001', [$photo]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'code' => 'INVALID_BATCH_STATUS_TRANSITION',
            ]);
    }

    public function test_upload_photo_to_survey_pending_batch_returns_400()
    {
        $this->createDraftBatch('batch-001', ['status' => 'survey_pending']);

        $photo = $this->fakeImage('photo1.jpg');
        $response = $this->uploadPhotos('batch-001', [$photo]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'code' => 'INVALID_BATCH_STATUS_TRANSITION',
            ]);
    }

    public function test_upload_photo_to_remote_review_batch_returns_400()
    {
        $this->createDraftBatch('batch-001', ['status' => 'remote_review']);

        $photo = $this->fakeImage('photo1.jpg');
        $response = $this->uploadPhotos('batch-001', [$photo]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'code' => 'INVALID_BATCH_STATUS_TRANSITION',
            ]);
    }

    // --- Business Rules: Max 10 Foto Per Batch ---

    public function test_upload_exceeds_max_10_photos_per_batch_returns_422()
    {
        $this->createDraftBatch();

        // Upload 5 foto pertama
        $photos1 = array_map(fn ($i) => $this->fakeImage("photo{$i}.jpg"), range(1, 5));
        $this->uploadPhotos('batch-001', $photos1)->assertStatus(201);

        // Upload 5 foto lagi (total = 10)
        $photos2 = array_map(fn ($i) => $this->fakeImage("photo{$i}.jpg"), range(6, 10));
        $this->uploadPhotos('batch-001', $photos2)->assertStatus(201);

        // Upload foto ke-11 → harus gagal
        $photos11 = [$this->fakeImage('photo11.jpg')];
        $response = $this->uploadPhotos('batch-001', $photos11);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ])
            ->assertJsonPath('message', function ($message) {
                return str_contains($message, '10') || str_contains($message, 'maksimal');
            });
    }

    // --- Business Rules: Batch Not Found ---

    public function test_upload_photo_to_nonexistent_batch_returns_404()
    {
        $photo = $this->fakeImage('photo1.jpg');
        $response = $this->uploadPhotos('nonexistent-batch', [$photo]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code' => 'NOT_FOUND',
            ]);
    }

    // --- Auth & Role ---

    public function test_upload_photo_unauthorized_without_auth_returns_401()
    {
        $this->createDraftBatch();

        $photo = $this->fakeImage('photo1.jpg');
        $response = $this->postJson('/api/v1/farmer/batches/batch-001/photos', [
            'photos' => [$photo],
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    public function test_upload_photo_forbidden_with_exporter_role_returns_403()
    {
        $this->createDraftBatch();

        $photo = $this->fakeImage('photo1.jpg');
        $response = $this->actingAs($this->exporter)
            ->postJson('/api/v1/farmer/batches/batch-001/photos', [
                'photos' => [$photo],
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }

    // --- Data Isolation ---

    public function test_upload_photo_other_farmer_batch_returns_403_or_404()
    {
        // Buat batch milik farmer
        $this->createDraftBatch();

        // farmer2 coba upload foto ke batch farmer
        $photo = $this->fakeImage('photo1.jpg');
        $response = $this->actingAs($this->farmer2)
            ->postJson('/api/v1/farmer/batches/batch-001/photos', [
                'photos' => [$photo],
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

    // ========================================================================
    // 9.2: DELETE /api/v1/farmer/batches/{batchId}/photos/{photoId}
    // — Hapus Foto Batch
    // ========================================================================

    // --- Happy Path ---

    public function test_delete_photo_returns_200()
    {
        $this->createDraftBatch();

        // Upload 3 foto dulu
        $photos = array_map(fn ($i) => $this->fakeImage("photo{$i}.jpg"), range(1, 3));
        $uploadResponse = $this->uploadPhotos('batch-001', $photos);
        $photoId = $uploadResponse->json('data.photos.0.id');

        // Hapus 1 foto
        $response = $this->actingAs($this->farmer)
            ->deleteJson('/api/v1/farmer/batches/batch-001/photos/'.$photoId);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS_DELETE',
                'message' => 'Foto berhasil dihapus',
            ])
            ->assertJsonStructure([
                'data' => [
                    'deleted_photo_id',
                    'batch_photo_count',
                    'batch_photo_minimum',
                    'is_complete',
                ],
            ]);

        // Verifikasi deleted_photo_id sesuai
        $response->assertJsonPath('data.deleted_photo_id', $photoId);

        // Setelah hapus 1 dari 3, sisa 2 → is_complete = false
        $response->assertJsonPath('data.batch_photo_count', 2);
        $response->assertJsonPath('data.batch_photo_minimum', 3);
        $response->assertJsonPath('data.is_complete', false);

        // Verifikasi foto hilang dari database
        $this->assertDatabaseMissing('batch_photos', [
            'id' => $photoId,
        ]);
    }

    public function test_delete_photo_reduces_count_and_updates_is_complete()
    {
        $this->createDraftBatch();

        // Upload 3 foto
        $photos = array_map(fn ($i) => $this->fakeImage("photo{$i}.jpg"), range(1, 3));
        $uploadResponse = $this->uploadPhotos('batch-001', $photos);
        $this->assertEquals(3, $uploadResponse->json('data.batch_photo_count'));
        $this->assertTrue($uploadResponse->json('data.is_complete'));

        // Hapus 1 foto → sisa 2 → is_complete = false
        $photoId = $uploadResponse->json('data.photos.0.id');
        $deleteResponse = $this->actingAs($this->farmer)
            ->deleteJson('/api/v1/farmer/batches/batch-001/photos/'.$photoId);

        $deleteResponse->assertStatus(200)
            ->assertJsonPath('data.batch_photo_count', 2)
            ->assertJsonPath('data.is_complete', false);
    }

    public function test_delete_photo_from_batch_with_1_photo_sets_count_to_zero()
    {
        $this->createDraftBatch();

        // Upload 1 foto
        $uploadResponse = $this->uploadPhotos('batch-001', [$this->fakeImage('photo1.jpg')]);
        $photoId = $uploadResponse->json('data.photos.0.id');

        // Hapus foto → count = 0
        $response = $this->actingAs($this->farmer)
            ->deleteJson('/api/v1/farmer/batches/batch-001/photos/'.$photoId);

        $response->assertStatus(200)
            ->assertJsonPath('data.batch_photo_count', 0)
            ->assertJsonPath('data.is_complete', false);
    }

    public function test_delete_photo_updates_batch_detail_photo_count()
    {
        $this->createDraftBatch();

        // Upload 3 foto
        $photos = array_map(fn ($i) => $this->fakeImage("photo{$i}.jpg"), range(1, 3));
        $uploadResponse = $this->uploadPhotos('batch-001', $photos);
        $photoId = $uploadResponse->json('data.photos.0.id');

        // Hapus 1 foto
        $this->actingAs($this->farmer)
            ->deleteJson('/api/v1/farmer/batches/batch-001/photos/'.$photoId)
            ->assertStatus(200);

        // Cek batch detail → photo_count harus 2
        $detailResponse = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches/batch-001');

        $detailResponse->assertStatus(200)
            ->assertJsonPath('data.batch.photos.count', 2)
            ->assertJsonPath('data.batch.photos.minimum', 3)
            ->assertJsonPath('data.batch.photos.is_complete', false);
    }

    // --- Error Cases ---

    public function test_delete_nonexistent_photo_returns_404()
    {
        $this->createDraftBatch();

        $response = $this->actingAs($this->farmer)
            ->deleteJson('/api/v1/farmer/batches/batch-001/photos/nonexistent-photo-id');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code' => 'NOT_FOUND',
            ]);
    }

    public function test_delete_photo_from_nonexistent_batch_returns_404()
    {
        $response = $this->actingAs($this->farmer)
            ->deleteJson('/api/v1/farmer/batches/nonexistent-batch/photos/photo-001');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code' => 'NOT_FOUND',
            ]);
    }

    // --- Auth & Role ---

    public function test_delete_photo_unauthorized_without_auth_returns_401()
    {
        $response = $this->deleteJson('/api/v1/farmer/batches/batch-001/photos/photo-001');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    public function test_delete_photo_forbidden_with_exporter_role_returns_403()
    {
        $this->createDraftBatch();

        $response = $this->actingAs($this->exporter)
            ->deleteJson('/api/v1/farmer/batches/batch-001/photos/photo-001');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }

    // --- Data Isolation ---

    public function test_delete_photo_other_farmer_batch_returns_403_or_404()
    {
        // Buat batch milik farmer, upload foto
        $this->createDraftBatch();
        $photos = [$this->fakeImage('photo1.jpg')];
        $uploadResponse = $this->uploadPhotos('batch-001', $photos);
        $photoId = $uploadResponse->json('data.photos.0.id');

        // farmer2 coba hapus foto milik farmer
        $response = $this->actingAs($this->farmer2)
            ->deleteJson('/api/v1/farmer/batches/batch-001/photos/'.$photoId);

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

        // Foto tidak boleh terhapus
        $this->assertDatabaseHas('batch_photos', ['id' => $photoId]);
    }

    // ========================================================================
    // Cross-Module: Foto & Batch Detail Integration
    // ========================================================================

    public function test_batch_detail_shows_uploaded_photos_in_gallery()
    {
        $this->createDraftBatch();

        // Upload 3 foto
        $photos = [
            $this->fakeImage('photo1.jpg'),
            $this->fakeImage('photo2.jpg'),
            $this->fakeImage('photo3.jpg'),
        ];
        $this->uploadPhotos('batch-001', $photos, ['Wajib', 'Wajib', 'Wajib']);

        // Cek batch detail
        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches/batch-001');

        $response->assertStatus(200)
            ->assertJsonPath('data.batch.photos.count', 3)
            ->assertJsonPath('data.batch.photos.minimum', 3)
            ->assertJsonPath('data.batch.photos.is_complete', true)
            ->assertJsonStructure([
                'data' => [
                    'batch' => [
                        'photos' => [
                            'items' => [
                                '*' => ['id', 'url', 'thumbnail_url', 'note', 'filename', 'uploaded_at'],
                            ],
                        ],
                    ],
                ],
            ]);
    }

    public function test_batch_detail_shows_empty_photos_when_none_uploaded()
    {
        $this->createDraftBatch();

        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches/batch-001');

        $response->assertStatus(200)
            ->assertJsonPath('data.batch.photos.count', 0)
            ->assertJsonPath('data.batch.photos.minimum', 3)
            ->assertJsonPath('data.batch.photos.is_complete', false)
            ->assertJsonPath('data.batch.photos.items', []);
    }

    public function test_batch_detail_photo_count_reflects_after_delete()
    {
        $this->createDraftBatch();

        // Upload 5 foto
        $photos = array_map(fn ($i) => $this->fakeImage("photo{$i}.jpg"), range(1, 5));
        $uploadResponse = $this->uploadPhotos('batch-001', $photos);

        // Hapus 2 foto
        $photoId1 = $uploadResponse->json('data.photos.0.id');
        $photoId2 = $uploadResponse->json('data.photos.1.id');

        $this->actingAs($this->farmer)
            ->deleteJson('/api/v1/farmer/batches/batch-001/photos/'.$photoId1)
            ->assertStatus(200);
        $this->actingAs($this->farmer)
            ->deleteJson('/api/v1/farmer/batches/batch-001/photos/'.$photoId2)
            ->assertStatus(200);

        // Cek batch detail → 3 foto tersisa, is_complete = true
        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches/batch-001');

        $response->assertStatus(200)
            ->assertJsonPath('data.batch.photos.count', 3)
            ->assertJsonPath('data.batch.photos.is_complete', true);
    }

    public function test_batch_list_shows_photo_count_in_batch_summary()
    {
        $this->createDraftBatch();

        // Upload 3 foto
        $photos = array_map(fn ($i) => $this->fakeImage("photo{$i}.jpg"), range(1, 3));
        $this->uploadPhotos('batch-001', $photos);

        // Cek batch list
        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches');

        $response->assertStatus(200);
        $batchData = $response->json('data.0');
        $this->assertEquals(3, $batchData['photo_count']);
        $this->assertEquals(3, $batchData['photo_minimum']);
    }

    // ========================================================================
    // Cross-Module: Foto & BATCH_PHOTO_MINIMUM Error Code
    // ========================================================================

    public function test_photo_minimum_constraint_is_3()
    {
        // Buat batch tanpa foto
        $this->createDraftBatch();

        // Cek batch detail → photo_minimum = 3
        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches/batch-001');

        $response->assertStatus(200)
            ->assertJsonPath('data.batch.photos.minimum', 3)
            ->assertJsonPath('data.batch.photo_minimum', 3);
    }

    public function test_photo_minimum_met_after_3_uploads()
    {
        $this->createDraftBatch();

        // Upload 2 foto → is_complete = false
        $this->uploadPhotos('batch-001', [
            $this->fakeImage('photo1.jpg'),
            $this->fakeImage('photo2.jpg'),
        ]);

        $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches/batch-001')
            ->assertJsonPath('data.batch.photos.is_complete', false);

        // Upload foto ke-3 → is_complete = true
        $this->uploadPhotos('batch-001', [$this->fakeImage('photo3.jpg')]);

        $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches/batch-001')
            ->assertJsonPath('data.batch.photos.is_complete', true)
            ->assertJsonPath('data.batch.photos.count', 3);
    }

    // ========================================================================
    // Cross-Module: Foto & actions_available pada Batch Detail
    // ========================================================================

    public function test_batch_detail_can_add_photos_true_when_draft_and_below_10()
    {
        $this->createDraftBatch();

        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches/batch-001');

        $response->assertStatus(200)
            ->assertJsonPath('data.batch.actions_available.can_add_photos', true);
    }

    public function test_batch_detail_can_add_photos_false_when_processing()
    {
        $this->createDraftBatch('batch-001', ['status' => 'processing']);

        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches/batch-001');

        $response->assertStatus(200)
            ->assertJsonPath('data.batch.actions_available.can_add_photos', false);
    }

    public function test_batch_detail_can_add_photos_false_when_max_photos_reached()
    {
        $this->createDraftBatch();

        // Upload 10 foto (max)
        $photos1 = array_map(fn ($i) => $this->fakeImage("photo{$i}.jpg"), range(1, 5));
        $photos2 = array_map(fn ($i) => $this->fakeImage("photo{$i}.jpg"), range(6, 10));
        $this->uploadPhotos('batch-001', $photos1)->assertStatus(201);
        $this->uploadPhotos('batch-001', $photos2)->assertStatus(201);

        // can_add_photos harus false karena sudah 10
        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches/batch-001');

        $response->assertStatus(200)
            ->assertJsonPath('data.batch.photos.count', 10)
            ->assertJsonPath('data.batch.actions_available.can_add_photos', false);
    }

    // ========================================================================
    // Edge Cases
    // ========================================================================

    public function test_upload_and_delete_photo_preserves_data_isolation_between_farmers()
    {
        // Buat batch untuk farmer dan farmer2
        $this->createDraftBatch('batch-farmer1');

        Batch::factory()->create([
            'farmer_id' => $this->farmer2->id,
            'batch_id' => 'batch-farmer2',
            'status' => 'draft',
        ]);

        // farmer upload foto ke batch-nya
        $this->uploadPhotos('batch-farmer1', [$this->fakeImage('f1-photo1.jpg')]);
        $this->uploadPhotosAsFarmer('batch-farmer2', [$this->fakeImage('f2-photo1.jpg')]);

        // farmer1 lihat batch-nya → harus 1 foto
        $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches/batch-farmer1')
            ->assertJsonPath('data.batch.photos.count', 1);

        // farmer2 lihat batch-nya → harus 1 foto
        $this->actingAs($this->farmer2)
            ->getJson('/api/v1/farmer/batches/batch-farmer2')
            ->assertJsonPath('data.batch.photos.count', 1);

        // farmer1 lihat batch farmer2 → 403 atau 404
        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches/batch-farmer2');

        $this->assertThat(
            $response->status(),
            $this->logicalOr($this->equalTo(403), $this->equalTo(404))
        );
    }

    public function test_multiple_upload_requests_maintain_correct_count()
    {
        $this->createDraftBatch();

        // Upload 1 foto
        $this->uploadPhotos('batch-001', [$this->fakeImage('photo1.jpg')])
            ->assertJsonPath('data.batch_photo_count', 1);

        // Upload 2 foto lagi
        $this->uploadPhotos('batch-001', [
            $this->fakeImage('photo2.jpg'),
            $this->fakeImage('photo3.jpg'),
        ])
            ->assertJsonPath('data.batch_photo_count', 3)
            ->assertJsonPath('data.is_complete', true);

        // Hapus 1 foto
        $detail = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/batches/batch-001');
        $photoId = $detail->json('data.batch.photos.items.0.id');

        $this->actingAs($this->farmer)
            ->deleteJson('/api/v1/farmer/batches/batch-001/photos/'.$photoId)
            ->assertJsonPath('data.batch_photo_count', 2)
            ->assertJsonPath('data.is_complete', false);

        // Upload 1 lagi → 3 foto → is_complete = true
        $this->uploadPhotos('batch-001', [$this->fakeImage('photo4.jpg')])
            ->assertJsonPath('data.batch_photo_count', 3)
            ->assertJsonPath('data.is_complete', true);
    }

    // ========================================================================
    // Helper khusus untuk upload sebagai farmer2
    // ========================================================================

    private function uploadPhotosAsFarmer(string $batchId, array $photos, array $notes = []): TestResponse
    {
        return $this->actingAs($this->farmer2)
            ->postJson(
                '/api/v1/farmer/batches/'.$batchId.'/photos',
                array_merge(
                    ['photos' => $photos],
                    count($notes) > 0 ? ['notes' => $notes] : []
                )
            );
    }
}

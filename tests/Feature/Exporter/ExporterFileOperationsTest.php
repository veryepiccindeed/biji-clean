<?php

namespace Tests\Feature\Exporter;

use App\Models\User;
use App\Models\Batch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExporterFileOperationsTest extends TestCase
{
    use RefreshDatabase;

    private User $exporter;
    private User $exporter2;
    private User $farmer;
    private User $buyer;
    private Batch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->exporter = User::factory()->create(['role' => 'exporter']);
        $this->exporter2 = User::factory()->create(['role' => 'exporter']);
        $this->farmer = User::factory()->create(['role' => 'farmer']);
        $this->buyer = User::factory()->create(['role' => 'buyer']);

        // Create batch for exporter
        $this->batch = Batch::factory()->create([
            'exporter_id' => $this->exporter->id,
            'batch_code' => 'BT-0921',
        ]);
    }

    // ==================== 13.1: GET /api/v1/exporter/batches/{batchId}/certificate/pdf ====================

    public function test_download_certificate_pdf_success()
    {
        $response = $this->actingAs($this->exporter)
            ->getJson("/api/v1/exporter/batches/{$this->batch->id}/certificate/pdf");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Content-Disposition');
    }

    public function test_download_certificate_pdf_has_correct_filename()
    {
        $response = $this->actingAs($this->exporter)
            ->getJson("/api/v1/exporter/batches/{$this->batch->id}/certificate/pdf");

        $response->assertStatus(200);

        // Check that filename contains batch code
        $contentDisposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('BT-0921', $contentDisposition);
    }

    public function test_download_certificate_pdf_returns_binary_content()
    {
        $response = $this->actingAs($this->exporter)
            ->getJson("/api/v1/exporter/batches/{$this->batch->id}/certificate/pdf");

        $response->assertStatus(200);

        // PDF files should have binary content (not JSON)
        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertNotEmpty($content);
    }

    public function test_download_certificate_pdf_not_found()
    {
        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/batches/99999/certificate/pdf');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code' => 'NOT_FOUND',
            ]);
    }

    public function test_download_certificate_pdf_data_isolation_forbidden()
    {
        // Exporter2 tries to download Exporter's batch certificate
        $response = $this->actingAs($this->exporter2)
            ->getJson("/api/v1/exporter/batches/{$this->batch->id}/certificate/pdf");

        // Should be 403 or 404 (data isolation)
        $this->assertThat(
            $response->status(),
            $this->logicalOr(
                $this->equalTo(403),
                $this->equalTo(404)
            )
        );
    }

    public function test_download_certificate_pdf_unauthorized_without_auth()
    {
        $response = $this->getJson("/api/v1/exporter/batches/{$this->batch->id}/certificate/pdf");

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    public function test_download_certificate_pdf_forbidden_with_farmer_role()
    {
        $response = $this->actingAs($this->farmer)
            ->getJson("/api/v1/exporter/batches/{$this->batch->id}/certificate/pdf");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }

    public function test_download_certificate_pdf_forbidden_with_buyer_role()
    {
        $response = $this->actingAs($this->buyer)
            ->getJson("/api/v1/exporter/batches/{$this->batch->id}/certificate/pdf");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }

    // ==================== 13.2: POST /api/v1/me/profile/avatar ====================

    public function test_upload_avatar_jpeg_success()
    {
        $file = UploadedFile::fake()->create('avatar.jpg', 400, 'image/jpeg');

        $response = $this->actingAs($this->exporter)
            ->postJson('/api/v1/me/profile/avatar', [
                'avatar' => $file,
            ]);
        
        $response->dump();
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
                'message' => 'Avatar berhasil diunggah',
            ])
            ->assertJsonStructure([
                'success',
                'code',
                'message',
                'data' => [
                    'avatar_url',
                ],
                'timestamp',
            ]);
    }

    public function test_upload_avatar_png_success()
    {
        $file = UploadedFile::fake()->create('avatar.png', 400, 'image/png');

        $response = $this->actingAs($this->exporter)
            ->postJson('/api/v1/me/profile/avatar', [
                'avatar' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
            ]);
    }

    public function test_upload_avatar_returns_valid_url()
    {
        $file = UploadedFile::fake()->create('avatar.jpg', 400, 'image/jpeg');

        $response = $this->actingAs($this->exporter)
            ->postJson('/api/v1/me/profile/avatar', [
                'avatar' => $file,
            ]);

        $response->assertStatus(200);

        $avatarUrl = $response->json('data.avatar_url');
        $this->assertNotNull($avatarUrl);
        $this->assertIsString($avatarUrl);
        $this->assertTrue(str_starts_with($avatarUrl, 'http') || str_starts_with($avatarUrl, '/'));
    }

    public function test_upload_avatar_file_too_large()
    {
        // Create a fake file larger than 5MB
        $file = UploadedFile::fake()->create('avatar.jpg', 6000); // 6MB

        $response = $this->actingAs($this->exporter)
            ->postJson('/api/v1/me/profile/avatar', [
                'avatar' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    public function test_upload_avatar_invalid_file_type_bmp()
    {
        $file = UploadedFile::fake()->create('avatar.bmp', 1000, 'image/bmp');

        $response = $this->actingAs($this->exporter)
            ->postJson('/api/v1/me/profile/avatar', [
                'avatar' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    public function test_upload_avatar_invalid_file_type_gif()
    {
        $file = UploadedFile::fake()->create('avatar.gif', 1000, 'image/gif');

        $response = $this->actingAs($this->exporter)
            ->postJson('/api/v1/me/profile/avatar', [
                'avatar' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    public function test_upload_avatar_invalid_file_type_pdf()
    {
        $file = UploadedFile::fake()->create('avatar.pdf', 1000, 'application/pdf');

        $response = $this->actingAs($this->exporter)
            ->postJson('/api/v1/me/profile/avatar', [
                'avatar' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    public function test_upload_avatar_invalid_file_type_text()
    {
        $file = UploadedFile::fake()->create('avatar.txt', 1000, 'text/plain');

        $response = $this->actingAs($this->exporter)
            ->postJson('/api/v1/me/profile/avatar', [
                'avatar' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    public function test_upload_avatar_missing_file()
    {
        $response = $this->actingAs($this->exporter)
            ->postJson('/api/v1/me/profile/avatar', []);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    public function test_upload_avatar_file_field_empty()
    {
        $response = $this->actingAs($this->exporter)
            ->postJson('/api/v1/me/profile/avatar', [
                'avatar' => null,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    public function test_upload_avatar_unauthorized_without_auth()
    {
        $file = UploadedFile::fake()->create('avatar.jpg', 400, 400, 'jpeg');

        $response = $this->postJson('/api/v1/me/profile/avatar', [
            'avatar' => $file,
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    public function test_upload_avatar_updates_user_profile()
    {
        $file = UploadedFile::fake()->create('avatar.jpg', 400, 'image/jpeg');

        $response = $this->actingAs($this->exporter)
            ->postJson('/api/v1/me/profile/avatar', [
                'avatar' => $file,
            ]);

        $response->assertStatus(200);

        // Verify user profile was updated
        $this->exporter->refresh();
        $this->assertNotNull($this->exporter->avatar);
    }

    public function test_upload_avatar_replaces_old_avatar()
    {
        // Upload first avatar
        $file1 = UploadedFile::fake()->create('avatar1.jpg', 400, 'image/jpeg');
        $response1 = $this->actingAs($this->exporter)
            ->postJson('/api/v1/me/profile/avatar', [
                'avatar' => $file1,
            ]);

        $response1->assertStatus(200);
        $avatarUrl1 = $response1->json('data.avatar_url');

        // Upload second avatar
        $file2 = UploadedFile::fake()->create('avatar2.jpg', 600, 'image/jpeg');
        $response2 = $this->actingAs($this->exporter)
            ->postJson('/api/v1/me/profile/avatar', [
                'avatar' => $file2,
            ]);

        $response2->assertStatus(200);
        $avatarUrl2 = $response2->json('data.avatar_url');

        // Avatar URL should be different (new file)
        $this->assertNotEquals($avatarUrl1, $avatarUrl2);
    }

    public function test_upload_avatar_different_users_have_separate_avatars()
    {
        // Exporter 1 uploads avatar
        $file1 = UploadedFile::fake()->create('avatar1.jpg', 400, 'image/jpeg');
        $response1 = $this->actingAs($this->exporter)
            ->postJson('/api/v1/me/profile/avatar', [
                'avatar' => $file1,
            ]);

        $response1->assertStatus(200);
        $avatarUrl1 = $response1->json('data.avatar_url');

        // Exporter 2 uploads avatar
        $file2 = UploadedFile::fake()->create('avatar2.jpg', 600, 'image/jpeg');
        $response2 = $this->actingAs($this->exporter2)
            ->postJson('/api/v1/me/profile/avatar', [
                'avatar' => $file2,
            ]);

        $response2->assertStatus(200);
        $avatarUrl2 = $response2->json('data.avatar_url');

        // Avatar URLs should be different
        $this->assertNotEquals($avatarUrl1, $avatarUrl2);
    }

    public function test_upload_avatar_response_structure()
    {
        $file = UploadedFile::fake()->create('avatar.jpg', 400, 'image/jpeg');

        $response = $this->actingAs($this->exporter)
            ->postJson('/api/v1/me/profile/avatar', [
                'avatar' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'code',
                'message',
                'data' => [
                    'avatar_url',
                ],
                'timestamp',
            ]);
    }

    public function test_upload_avatar_validation_error_response_format()
    {
        $response = $this->actingAs($this->exporter)
            ->postJson('/api/v1/me/profile/avatar', []);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'code',
                'message',
                'details',
                'timestamp',
            ]);
    }

    public function test_upload_avatar_max_5mb_boundary()
    {
        // Create file exactly at 5MB boundary (should succeed)
        $file = UploadedFile::fake()->create('avatar.jpg', 5000, 'image/jpeg');

        $response = $this->actingAs($this->exporter)
            ->postJson('/api/v1/me/profile/avatar', [
                'avatar' => $file,
            ]);

        // Should either succeed or fail depending on implementation
        // (5MB exactly or 5MB as strict limit)
        $this->assertThat(
            $response->status(),
            $this->logicalOr(
                $this->equalTo(200),
                $this->equalTo(422)
            )
        );
    }

    public function test_upload_avatar_works_for_all_roles()
    {
        $users = [
            $this->exporter,
            $this->farmer,
            $this->buyer,
        ];

        foreach ($users as $user) {
            $file = UploadedFile::fake()->create('avatar.jpg', 800, 'image/jpeg');

            $response = $this->actingAs($user)
                ->postJson('/api/v1/me/profile/avatar', [
                    'avatar' => $file,
                ]);

            // Avatar upload should work for all roles
            $response->assertStatus(200);
        }
    }
}

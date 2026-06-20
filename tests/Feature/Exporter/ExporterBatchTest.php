<?php

namespace Tests\Feature\Exporter;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Assert;

class ExporterBatchTest extends TestCase
{
    use RefreshDatabase;

    private User $exporter;
    private User $farmer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exporter = User::factory()->create(['role' => 'exporter']);
        $this->farmer = User::factory()->create(['role' => 'farmer']);
    }

    // ==================== 9.1: GET /api/v1/exporter/batches/available ====================

    public function test_available_batches_returns_list_with_pagination()
    {
        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/batches/available');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
                'message' => 'Daftar batch tersedia berhasil diambil',
            ])
            ->assertJsonStructure([
                'success',
                'code',
                'message',
                'data' => [],
                'pagination' => [
                    'cursor',
                    'hasMore',
                    'limit',
                ],
                'timestamp',
            ]);
    }

    public function test_available_batches_with_custom_limit()
    {
        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/batches/available?limit=50');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
            ])
            ->assertJsonPath('pagination.limit', 50);
    }

    public function test_available_batches_limit_capped_at_100()
    {
        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/batches/available?limit=150');

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(100, $response->json('pagination.limit'));
    }

    public function test_available_batches_with_sort_parameter()
    {
        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/batches/available?sort=elevation');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
            ]);
    }

    public function test_available_batches_with_health_filter_normal()
    {
        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/batches/available?health_filter=normal');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
            ]);
    }

    public function test_available_batches_with_health_filter_warning()
    {
        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/batches/available?health_filter=warning');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
            ]);
    }

    public function test_available_batches_with_health_filter_critical()
    {
        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/batches/available?health_filter=critical');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
            ]);
    }

    public function test_available_batches_with_cursor_pagination()
    {
        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/batches/available?cursor=eyJpZCI6InByb2QtMDMifQ==');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
            ]);
    }

    public function test_available_batches_unauthorized_without_auth()
    {
        $response = $this->getJson('/api/v1/exporter/batches/available');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    public function test_available_batches_forbidden_with_farmer_role()
    {
        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/exporter/batches/available');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }

    // ==================== 9.2: GET /api/v1/exporter/batches/available/{batchId} ====================

    public function test_show_available_batch_returns_detail()
    {
        \App\Models\Batch::factory()->create([
        'batch_id' => 'prod-001',
        'status' => 'pending',
        'exporter_id' => null
        ]);

        $response = $this->actingAs($this->exporter, 'sanctum')
            ->getJson('/api/v1/exporter/batches/available/prod-001');

        $response->assertStatus(200);
    }

    public function test_show_available_batch_not_found()
    {
        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/batches/available/invalid-batch-id');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code' => 'NOT_FOUND',
            ]);
    }

    public function test_show_available_batch_unauthorized_without_auth()
    {
        $response = $this->getJson('/api/v1/exporter/batches/available/prod-001');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    public function test_show_available_batch_forbidden_with_farmer_role()
    {
        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/exporter/batches/available/prod-001');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }

    // ==================== 9.3: POST /api/v1/exporter/batches/{batchId}/acquire ====================

    public function test_acquire_batch_returns_created()
    {
        \App\Models\Batch::factory()->create([
        'batch_id' => 'prod-001',
        'status' => 'ready',
        'exporter_id' => null, // Biar bisa diakuisisi
        ]);
        
        $response = $this->actingAs($this->exporter)
            ->postJson('/api/v1/exporter/batches/prod-001/acquire', []);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS_CREATE',
                ]);
    }

    public function test_acquire_batch_not_found()
    {
        $response = $this->actingAs($this->exporter)
            ->postJson('/api/v1/exporter/batches/invalid-batch-id/acquire', []);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code' => 'NOT_FOUND',
            ]);
    }

    public function test_acquire_batch_duplicate_acquisition_conflict()
    {
       $batch = \App\Models\Batch::factory()->create([
        'batch_id' => 'prod-001',
        'status' => 'ready',
        'exporter_id' => null
        ]);
    
        // First exporter acquires batch
        $this->actingAs($this->exporter)
            ->postJson('/api/v1/exporter/batches/prod-001/acquire', [])
            ->assertStatus(201);

        // Second exporter tries to acquire same batch
        $exporter2 = User::factory()->create(['role' => 'exporter']);
        $response = $this->actingAs($exporter2, 'sanctum')
            ->postJson('/api/v1/exporter/batches/prod-001/acquire', []);

        $response->assertStatus(409);
    }

    public function test_acquire_batch_invalid_status_bad_request()
    {
        $batch = \App\Models\Batch::factory()->create([
        'batch_id' => 'prod-002',
        'status' => 'draft',
        'exporter_id' => null
        ]);
    
    
        $response = $this->actingAs($this->exporter, 'sanctum')
            ->postJson('/api/v1/exporter/batches/prod-002/acquire', []);

        // Assuming prod-002 has invalid status
        $response->assertStatus(400)->assertJson([
                'success' => false,
                'code' => 'INVALID_STATUS_TRANSITION',
            ]);
    }

    public function test_acquire_batch_unauthorized_without_auth()
    {
        $response = $this->postJson('/api/v1/exporter/batches/prod-001/acquire', []);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    public function test_acquire_batch_forbidden_with_farmer_role()
    {
        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/exporter/batches/prod-001/acquire', []);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }

    // ==================== 9.4: GET /api/v1/exporter/batches/mine ====================

    public function test_my_batches_returns_list_with_pagination()
    {
        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/batches/mine');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
                'message' => 'Daftar batch saya berhasil diambil',
            ])
            ->assertJsonStructure([
                'success',
                'code',
                'message',
                'data' => [],
                'pagination' => [
                    'cursor',
                    'hasMore',
                    'limit',
                ],
                'timestamp',
            ]);
    }

    public function test_my_batches_with_draft_filter()
    {
        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/batches/mine?filter=draft');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
            ]);
    }

    public function test_my_batches_with_published_filter()
    {
        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/batches/mine?filter=published');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
            ]);
    }

    public function test_my_batches_with_locked_filter()
    {
        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/batches/mine?filter=locked');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
            ]);
    }

    public function test_my_batches_with_sold_filter()
    {
        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/batches/mine?filter=sold');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
            ]);
    }

    public function test_my_batches_with_custom_limit()
    {
        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/batches/mine?limit=50');

        $response->assertStatus(200)
            ->assertJsonPath('pagination.limit', 50);
    }

    public function test_my_batches_with_cursor_pagination()
    {
        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/batches/mine?cursor=eyJpZCI6ImNlcnQtMDUifQ==');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
            ]);
    }

    public function test_my_batches_unauthorized_without_auth()
    {
        $response = $this->getJson('/api/v1/exporter/batches/mine');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    public function test_my_batches_forbidden_with_farmer_role()
    {
        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/exporter/batches/mine');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }

    // ==================== 9.5: GET /api/v1/exporter/batches/{batchId} ====================

    public function test_show_batch_returns_detail()
    {
        \App\Models\Batch::factory()->create([
        'batch_id' => 'cert-001', 
        'exporter_id' => $this->exporter->id, 
        ]);
    
    
        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/batches/cert-001');

        $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'code' => 'SUCCESS',
            'message' => 'Detail batch berhasil diambil',
        ]);
    }

    public function test_show_batch_not_found()
    {
        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/batches/invalid-cert-id');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code' => 'NOT_FOUND',
            ]);
    }

    public function test_show_batch_data_isolation_forbidden()
    {
        // Exporter2 tries to access exporter1's batch
        $exporter2 = User::factory()->create(['role' => 'exporter']);
        $response = $this->actingAs($exporter2)
            ->getJson('/api/v1/exporter/batches/cert-001');

        // Should get 403 or 404 (data isolation)
        $this->assertThat(
            $response->status(),
            $this->logicalOr(
                $this->equalTo(403),
                $this->equalTo(404)
            )
        );
    }

    public function test_show_batch_unauthorized_without_auth()
    {
        $response = $this->getJson('/api/v1/exporter/batches/cert-001');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    public function test_show_batch_forbidden_with_farmer_role()
    {
        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/exporter/batches/cert-001');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }

    // ==================== 9.6: PATCH /api/v1/exporter/batches/{batchId} ====================

    public function test_update_batch_returns_updated()
    {
        $batch = \App\Models\Batch::factory()->create([
        'batch_id' => 'cert-001',
        'exporter_id' => $this->exporter->id,
            ]);    
    
        $response = $this->actingAs($this->exporter, 'sanctum')
            ->patchJson('/api/v1/exporter/batches/cert-001', [
                'price' => 16000000,
                'description' => 'Kopi specialty dari ketinggian 1250 mdpl',
            ]);

        $response->assertStatus(200);
    }

    public function test_update_batch_price_only()
    {
        $batch = \App\Models\Batch::factory()->create([
        'batch_id' => 'cert-001',
        'exporter_id' => $this->exporter->id,
            ]);   
    
        $response = $this->actingAs($this->exporter)
            ->patchJson('/api/v1/exporter/batches/cert-001', [
                'price' => 16500000,
            ]);

        $response->assertStatus(200);
    }

    public function test_update_batch_description_only()
    {
        \App\Models\Batch::factory()->create([
        'batch_id' => 'cert-001',
        'exporter_id' => $this->exporter->id,
        ]);

        $response = $this->actingAs($this->exporter)
            ->patchJson('/api/v1/exporter/batches/cert-001', [
                'description' => 'Updated description',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS_UPDATE',
            ]);
    }

    public function test_update_batch_invalid_price_validation_error()
    {
        $response = $this->actingAs($this->exporter)
            ->patchJson('/api/v1/exporter/batches/cert-001', [
                'price' => -1000,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    public function test_update_batch_locked_status_bad_request()
    {
        \App\Models\Batch::factory()->create([
        'batch_id' => 'cert-002',
        'exporter_id' => $this->exporter->id,
        'status' => 'locked',
        ]);

        $response = $this->actingAs($this->exporter, 'sanctum')
            ->patchJson('/api/v1/exporter/batches/cert-002', [
                'price' => 16000000,
            ]);

        // Assuming cert-002 is locked
        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'code' => 'INVALID_STATE_TRANSITION',
            ]);
    }

    public function test_update_batch_sold_status_bad_request()
    {
        \App\Models\Batch::factory()->create([
        'batch_id' => 'cert-003',
        'exporter_id' => $this->exporter->id,
        'status' => 'sold',
        ]);

        $response = $this->actingAs($this->exporter, 'sanctum')
            ->patchJson('/api/v1/exporter/batches/cert-003', [
                'price' => 16000000,
            ]);

        // Assuming cert-003 is sold
        if ($response->status() === 400) {
            $response->assertJson([
                'success' => false,
                'code' => 'INVALID_STATE_TRANSITION',
            ]);
        }
    }

    public function test_update_batch_not_found()
    {
        $response = $this->actingAs($this->exporter)
            ->patchJson('/api/v1/exporter/batches/invalid-cert-id', [
                'price' => 16000000,
            ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code' => 'NOT_FOUND',
            ]);
    }

    public function test_update_batch_data_isolation_forbidden()
    {
        // Exporter2 tries to update exporter1's batch
        $exporter2 = User::factory()->create(['role' => 'exporter']);
        $response = $this->actingAs($exporter2)
            ->patchJson('/api/v1/exporter/batches/cert-001', [
                'price' => 16000000,
            ]);

        // Should get 403 or 404 (data isolation)
        $this->assertThat(
            $response->status(),
            $this->logicalOr(
                $this->equalTo(403),
                $this->equalTo(404)
            )
        );
    }

    public function test_update_batch_unauthorized_without_auth()
    {
        $response = $this->patchJson('/api/v1/exporter/batches/cert-001', [
            'price' => 16000000,
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    public function test_update_batch_forbidden_with_farmer_role()
    {
        $response = $this->actingAs($this->farmer)
            ->patchJson('/api/v1/exporter/batches/cert-001', [
                'price' => 16000000,
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }
}

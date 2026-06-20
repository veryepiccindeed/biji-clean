<?php

namespace Tests\Feature\Exporter;

use App\Models\Batch;
use App\Models\BlockchainLog;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EdgeCasesBusinessLogicTest extends TestCase
{
    use RefreshDatabase;

    protected User $exporter;
    protected User $otherExporter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->exporter = User::factory()->create(['role' => 'exporter']);
        $this->otherExporter = User::factory()->create(['role' => 'exporter']);
    }

    // =====================================================================
    // SECTION 1: Lifecycle Transitions (9+ tests)
    // =====================================================================

    public function test_cannot_acquire_batch_with_status_not_ready()
    {
        $batch = Batch::factory()->create([
            'status' => 'harvested',
            'price' => 50000,
        ]);

        $response = $this->actingAs($this->exporter)
            ->postJson('/api/v1/exporter/batches/' . $batch->id . '/acquire');

        $this->assertEquals(400, $response->status());
        $this->assertFalse($response->json('success'));
        $this->assertEquals('INVALID_STATUS_TRANSITION', $response->json('code'));
    }

    public function test_can_acquire_batch_with_status_ready()
    {
        $batch = Batch::factory()->create([
            'status' => 'ready',
            'price' => 50000,
        ]);

        $response = $this->actingAs($this->exporter)
            ->postJson('/api/v1/exporter/batches/' . $batch->id . '/acquire');

        $this->assertEquals(201, $response->status());
        $this->assertTrue($response->json('success'));

        $this->assertDatabaseHas('batches', [
            'id' => $batch->id,
            'status' => 'draft',
            'acquired_by' => $this->exporter->id,
        ]);
    }

    public function test_cannot_publish_batch_before_pdf_generated()
    {
        $batch = Batch::factory()->create([
            'status' => 'draft',
            'acquired_by' => $this->exporter->id,
            'certificate_pdf_path' => null,
        ]);

        $response = $this->actingAs($this->exporter)
            ->postJson('/api/v1/exporter/batches/' . $batch->id . '/certificate/publish');

        $this->assertEquals(400, $response->status());
        $this->assertFalse($response->json('success'));
        $this->assertEquals('INVALID_STATUS_TRANSITION', $response->json('code'));
    }

    public function test_can_publish_batch_after_pdf_generated()
    {
        $batch = Batch::factory()->create([
            'status' => 'draft',
            'acquired_by' => $this->exporter->id,
            'certificate_pdf_path' => 'batches/batch-123/certificate.pdf',
        ]);

        $response = $this->actingAs($this->exporter)
            ->postJson('/api/v1/exporter/batches/' . $batch->id . '/certificate/publish');

        $this->assertEquals(200, $response->status());
        $this->assertTrue($response->json('success'));
    }

    public function test_cannot_release_before_blockchain_published()
    {
        $batch = Batch::factory()->create([
            'status' => 'draft',
            'acquired_by' => $this->exporter->id,
            'blockchain_status' => 'pending',
        ]);

        $response = $this->actingAs($this->exporter)
            ->postJson('/api/v1/exporter/batches/' . $batch->id . '/release');

        $this->assertEquals(400, $response->status());
        $this->assertFalse($response->json('success'));
        $this->assertEquals('INVALID_STATUS_TRANSITION', $response->json('code'));
    }

    public function test_can_release_after_blockchain_published()
    {
        $batch = Batch::factory()->create([
            'status' => 'draft',
            'acquired_by' => $this->exporter->id,
            'blockchain_status' => 'published',
        ]);

        $response = $this->actingAs($this->exporter)
            ->postJson('/api/v1/exporter/batches/' . $batch->id . '/release');

        $this->assertEquals(200, $response->status());
        $this->assertTrue($response->json('success'));

        $this->assertDatabaseHas('batches', [
            'id' => $batch->id,
            'status' => 'released',
        ]);
    }

    public function test_cannot_release_already_released_batch()
    {
        $batch = Batch::factory()->create([
            'status' => 'released',
            'acquired_by' => $this->exporter->id,
        ]);

        $response = $this->actingAs($this->exporter)
            ->postJson('/api/v1/exporter/batches/' . $batch->id . '/release');

        $this->assertEquals(400, $response->status());
        $this->assertFalse($response->json('success'));
        $this->assertEquals('INVALID_STATUS_TRANSITION', $response->json('code'));
    }

    public function test_cannot_change_price_when_batch_locked()
    {
        $batch = Batch::factory()->create([
            'status' => 'locked',
            'acquired_by' => $this->exporter->id,
            'price' => 50000,
        ]);

        $response = $this->actingAs($this->exporter)
            ->patchJson('/api/v1/exporter/batches/' . $batch->id, [
                'price' => 60000,
            ]);

        $this->assertEquals(400, $response->status());
        $this->assertFalse($response->json('success'));
        $this->assertEquals('BATCH_LOCKED', $response->json('code'));
    }

    public function test_can_change_price_when_batch_not_locked()
    {
        $batch = Batch::factory()->create([
            'status' => 'released',
            'acquired_by' => $this->exporter->id,
            'price' => 50000,
        ]);

        $response = $this->actingAs($this->exporter)
            ->patchJson('/api/v1/exporter/batches/' . $batch->id, [
                'price' => 60000,
            ]);

        $this->assertEquals(200, $response->status());
        $this->assertTrue($response->json('success'));

        $this->assertDatabaseHas('batches', [
            'id' => $batch->id,
            'price' => 60000,
        ]);
    }

    // =====================================================================
    // SECTION 2: Authorization Constraints (5+ tests)
    // =====================================================================

    public function test_cannot_acquire_same_batch_twice()
    {
        $batch = Batch::factory()->create([
            'status' => 'ready',
            'price' => 50000,
        ]);

        // First acquisition
        $response1 = $this->actingAs($this->exporter)
            ->postJson('/api/v1/exporter/batches/' . $batch->id . '/acquire');

        $this->assertEquals(201, $response1->status());

        // Second acquisition attempt (same exporter)
        $response2 = $this->actingAs($this->exporter)
            ->postJson('/api/v1/exporter/batches/' . $batch->id . '/acquire');

        $this->assertEquals(409, $response2->status());
        $this->assertFalse($response2->json('success'));
        $this->assertEquals('DUPLICATE_ACQUISITION', $response2->json('code'));
    }

    public function test_cannot_acquire_batch_already_acquired_by_other()
    {
        $batch = Batch::factory()->create([
            'status' => 'ready',
            'acquired_by' => $this->otherExporter->id,
        ]);

        $response = $this->actingAs($this->exporter)
            ->postJson('/api/v1/exporter/batches/' . $batch->id . '/acquire');

        $this->assertEquals(409, $response->status());
        $this->assertFalse($response->json('success'));
        $this->assertEquals('DUPLICATE_ACQUISITION', $response->json('code'));
    }

    public function test_cannot_access_batch_acquired_by_other_exporter()
    {
        $batch = Batch::factory()->create([
            'status' => 'draft',
            'acquired_by' => $this->otherExporter->id,
        ]);

        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/batches/' . $batch->id);

        $this->assertEquals(404, $response->status());
        $this->assertFalse($response->json('success'));
    }

    public function test_cannot_modify_batch_from_other_exporter()
    {
        $batch = Batch::factory()->create([
            'status' => 'draft',
            'acquired_by' => $this->otherExporter->id,
            'price' => 50000,
        ]);

        $response = $this->actingAs($this->exporter)
            ->patchJson('/api/v1/exporter/batches/' . $batch->id, [
                'price' => 60000,
            ]);

        $this->assertEquals(403, $response->status());
        $this->assertFalse($response->json('success'));
        $this->assertEquals('FORBIDDEN', $response->json('code'));
    }

    public function test_can_access_own_acquired_batch()
    {
        $batch = Batch::factory()->create([
            'status' => 'draft',
            'acquired_by' => $this->exporter->id,
        ]);

        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/batches/' . $batch->id);

        $this->assertEquals(200, $response->status());
        $this->assertTrue($response->json('success'));
        $this->assertEquals($batch->id, $response->json('data.id'));
    }

    // =====================================================================
    // SECTION 3: Blockchain Constraints (5+ tests)
    // =====================================================================

    public function test_blockchain_job_starts_with_pending_status()
    {
        $batch = Batch::factory()->create([
            'status' => 'draft',
            'acquired_by' => $this->exporter->id,
        ]);

        $response = $this->actingAs($this->exporter)
            ->postJson('/api/v1/exporter/batches/' . $batch->id . '/certificate/generate', [
                'batch_id' => $batch->id,
            ]);

        $this->assertEquals(201, $response->status());
        $this->assertTrue($response->json('success'));
        $this->assertNotNull($response->json('data.blockchain_job_id'));
        $this->assertEquals('pending', $response->json('data.blockchain_status'));
    }

    public function test_blockchain_retry_logic()
    {
        $batch = Batch::factory()->create(['exporter_id' => $this->exporter->id]);
        
        $log = BlockchainLog::factory()->create([
            'status' => 'failed',
            'retry_count' => 2,
            'exporter_id' => $this->exporter->id, // Biar 'match' sama user yang login di tes
            'batch_id' => $batch->id // Sesuai requirement foreign key lu
        ]);

        $response = $this->actingAs($this->exporter)
            ->postJson('/api/v1/exporter/blockchain-logs/' . $log->id . '/retry');

        $this->assertEquals(202, $response->status());
        $this->assertTrue($response->json('success'));
        $this->assertEquals('pending', $response->json('data.log.status'));
        $this->assertEquals(3, $response->json('data.log.retry_count'));
    }

    public function test_cannot_retry_after_3_attempts()
    {
        $batch = Batch::factory()->create(['exporter_id' => $this->exporter->id]);
        $log = BlockchainLog::factory()->create([
            'status' => 'failed',
            'retry_count' => 3,
            'exporter_id' => $this->exporter->id,
            'batch_id' => $batch->id
        ]);

        $response = $this->actingAs($this->exporter)
            ->postJson('/api/v1/exporter/blockchain-logs/' . $log->id . '/retry');

        $this->assertEquals(400, $response->status());
        $this->assertFalse($response->json('success'));
        $this->assertEquals('RETRY_LIMIT_EXCEEDED', $response->json('code'));
    }

    public function test_cannot_retry_non_failed_log()
    {
        $batch = Batch::factory()->create(['exporter_id' => $this->exporter->id]);
        $log = BlockchainLog::factory()->create([
            'status' => 'published',
            'retry_count' => 0,
            'exporter_id' => $this->exporter->id, // Biar 'match' sama user yang login di tes
            'batch_id' => $batch->id // Sesuai requirement foreign key lu
        ]);

        $response = $this->actingAs($this->exporter)
            ->postJson('/api/v1/exporter/blockchain-logs/' . $log->id . '/retry');

        $this->assertEquals(400, $response->status());
        $this->assertFalse($response->json('success'));
        $this->assertEquals('CANNOT_RETRY_NON_FAILED', $response->json('code'));
    }

    public function test_blockchain_timeout_transition_to_failed()
    {
        $batch = Batch::factory()->create(['exporter_id' => $this->exporter->id]);
        $log = BlockchainLog::factory()->create([
            'status' => 'pending',
            'retry_count' => 0,
            'created_at' => now()->subMinutes(31),
            'exporter_id' => $this->exporter->id,
            'batch_id' => $batch->id
        ]);

        // Simulate timeout check (would be done by a scheduled job)
        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/exporter/blockchain-failure-logs');

        $this->assertEquals(200, $response->status());
        $this->assertTrue($response->json('success'));
        // Log should be included if timeout occurred
        $failedLogs = collect($response->json('data'))->pluck('id');
        $this->assertTrue($failedLogs->contains($log->id));
    }

    // =====================================================================
    // SECTION 4: Order & Payment Constraints (5+ tests)
    // =====================================================================

    public function test_cannot_place_order_if_batch_not_released()
    {
        $batch = Batch::factory()->create([
            'status' => 'ready',
            'acquired_by' => $this->exporter->id,
            'price' => 50000,
        ]);

        $buyer = User::factory()->create(['role' => 'buyer']);

        $response = $this->actingAs($buyer)
            ->postJson('/api/v1/buyer/orders', [
                'batch_id' => $batch->id,
                'quantity' => 10,
            ]);

        $this->assertEquals(400, $response->status());
        $this->assertFalse($response->json('success'));
        $this->assertEquals('BATCH_NOT_AVAILABLE', $response->json('code'));
    }

    public function test_cannot_place_order_if_batch_locked()
    {
        $batch = Batch::factory()->create([
            'status' => 'locked',
            'acquired_by' => $this->exporter->id,
            'price' => 50000,
        ]);

        $buyer = User::factory()->create(['role' => 'buyer']);

        $response = $this->actingAs($buyer)
            ->postJson('/api/v1/buyer/orders', [
                'batch_id' => $batch->id,
                'quantity' => 10,
            ]);

        $this->assertEquals(400, $response->status());
        $this->assertFalse($response->json('success'));
        $this->assertEquals('BATCH_LOCKED', $response->json('code'));
    }

    public function test_cannot_place_order_with_zero_price()
    {
        $batch = Batch::factory()->create([
            'status' => 'released',
            'acquired_by' => $this->exporter->id,
            'price' => 0,
        ]);

        $buyer = User::factory()->create(['role' => 'buyer']);

        $response = $this->actingAs($buyer)
            ->postJson('/api/v1/buyer/orders', [
                'batch_id' => $batch->id,
                'quantity' => 10,
            ]);

        $this->assertEquals(400, $response->status());
        $this->assertFalse($response->json('success'));
        $this->assertEquals('VALIDATION_ERROR', $response->json('code'));
    }

    public function test_batch_locks_after_order_placed()
    {
        $batch = Batch::factory()->create([
            'status' => 'released',
            'acquired_by' => $this->exporter->id,
            'price' => 50000,
        ]);

        $buyer = User::factory()->create(['role' => 'buyer']);

        $response = $this->actingAs($buyer)
            ->postJson('/api/v1/buyer/orders', [
                'batch_id' => $batch->id,
                'quantity' => 10,
            ]);

        $this->assertEquals(201, $response->status());
        $this->assertTrue($response->json('success'));

        $this->assertDatabaseHas('batches', [
            'id' => $batch->id,
            'status' => 'locked',
        ]);
    }

    public function test_can_place_order_on_released_batch_with_valid_price()
    {
        $batch = Batch::factory()->create([
            'status' => 'released',
            'acquired_by' => $this->exporter->id,
            'price' => 50000,
        ]);

        $buyer = User::factory()->create(['role' => 'buyer']);

        $response = $this->actingAs($buyer)
            ->postJson('/api/v1/buyer/orders', [
                'batch_id' => $batch->id,
                'quantity' => 10,
            ]);

        $this->assertEquals(201, $response->status());
        $this->assertTrue($response->json('success'));
        $this->assertNotNull($response->json('data.id'));
    }

    // =====================================================================
    // SECTION 5: State Isolation & Irreversible Transitions (3+ tests)
    // =====================================================================

    public function test_cannot_transition_released_batch_back_to_draft()
    {
        $batch = Batch::factory()->create([
            'status' => 'released',
            'acquired_by' => $this->exporter->id,
        ]);

        $response = $this->actingAs($this->exporter)
            ->patchJson('/api/v1/exporter/batches/' . $batch->id, [
                'status' => 'draft',
            ]);

        $this->assertEquals(400, $response->status());
        $this->assertFalse($response->json('success'));
        $this->assertEquals('INVALID_STATUS_TRANSITION', $response->json('code'));
    }

    public function test_sold_batch_cannot_be_re_released()
    {
        $batch = Batch::factory()->create([
            'status' => 'sold',
            'acquired_by' => $this->exporter->id,
        ]);

        $response = $this->actingAs($this->exporter)
            ->postJson('/api/v1/exporter/batches/' . $batch->id . '/release');

        $this->assertEquals(400, $response->status());
        $this->assertFalse($response->json('success'));
        $this->assertEquals('INVALID_STATUS_TRANSITION', $response->json('code'));
    }

    public function test_locked_batch_cannot_be_re_released()
    {
        $batch = Batch::factory()->create([
            'status' => 'locked',
            'acquired_by' => $this->exporter->id,
        ]);

        $response = $this->actingAs($this->exporter)
            ->postJson('/api/v1/exporter/batches/' . $batch->id . '/release');

        $this->assertEquals(400, $response->status());
        $this->assertFalse($response->json('success'));
        $this->assertEquals('INVALID_STATUS_TRANSITION', $response->json('code'));
    }

    // =====================================================================
    // SECTION 6: Combined Constraints (Additional Edge Cases)
    // =====================================================================

    public function test_cannot_acquire_batch_without_price()
    {
        $batch = Batch::factory()->create([
            'status' => 'ready',
            'price' => 0,
        ]);

        $response = $this->actingAs($this->exporter)
            ->postJson('/api/v1/exporter/batches/' . $batch->id . '/acquire');

        $this->assertEquals(400, $response->status());
        $this->assertFalse($response->json('success'));
        $this->assertEquals('VALIDATION_ERROR', $response->json('code'));
    }

    public function test_complete_workflow_lifecycle()
    {
        // 1. Create ready batch
        $batch = Batch::factory()->create([
            'status' => 'ready',
            'price' => 50000,
        ]);

        // 2. Acquire batch
        $acquire = $this->actingAs($this->exporter)
            ->postJson('/api/v1/exporter/batches/' . $batch->id . '/acquire');
        $this->assertEquals(201, $acquire->status());

        // 3. Generate & publish certificate (auto-triggers blockchain)
        $batch->refresh();
        $generate = $this->actingAs($this->exporter)
            ->postJson('/api/v1/exporter/batches/' . $batch->id . '/certificate/generate');
        $this->assertEquals(201, $generate->status());

        // 4. Simulate blockchain publish completion
        $batch->update([
            'certificate_pdf_path' => 'batches/batch-' . $batch->id . '/certificate.pdf',
            'blockchain_status' => 'published',
        ]);

        // 5. Release batch
        $release = $this->actingAs($this->exporter)
            ->postJson('/api/v1/exporter/batches/' . $batch->id . '/release');
        $this->assertEquals(200, $release->status());

        // 6. Place order (should lock batch)
        $buyer = User::factory()->create(['role' => 'buyer']);
        $order = $this->actingAs($buyer)
            ->postJson('/api/v1/buyer/orders', [
                'batch_id' => $batch->id,
                'quantity' => 10,
            ]);
        $this->assertEquals(201, $order->status());

        // 7. Verify batch is locked
        $this->assertDatabaseHas('batches', [
            'id' => $batch->id,
            'status' => 'locked',
        ]);
    }
}

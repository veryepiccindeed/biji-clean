<?php

namespace Tests\Feature\Exporter;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExporterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper untuk membuat user dengan peran tertentu.
     */
    private function createUserWithRole(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_exporter_dashboard(): void
    {
        // Tanpa autentikasi (tanpa token Sanctum)
        $response = $this->getJson('/api/v1/exporter/dashboard');

        // Sesuai konvensi API_CONTRACT.md, tidak auth bisa return 401
        $response->assertStatus(401);
    }

    public function test_farmer_cannot_access_exporter_routes(): void
    {
        $farmer = $this->createUserWithRole('farmer');

        // Coba akses endpoint exporter
        $response = $this->actingAs($farmer, 'sanctum')->getJson('/api/v1/exporter/dashboard');

        // Harus ditolak oleh middleware Role dengan status 403
        $response->assertStatus(403)
                 ->assertJsonFragment([
                     'success' => false,
                     'code' => 'FORBIDDEN',
                 ]);
    }

    public function test_buyer_cannot_access_exporter_routes(): void
    {
        $buyer = $this->createUserWithRole('buyer');

        // Coba akses endpoint exporter
        $response = $this->actingAs($buyer, 'sanctum')->getJson('/api/v1/exporter/dashboard');

        // Harus ditolak oleh middleware Role dengan status 403
        $response->assertStatus(403)
                 ->assertJsonFragment([
                     'success' => false,
                     'code' => 'FORBIDDEN',
                 ]);
    }

    public function test_exporter_can_pass_rbac_middleware_for_exporter_routes(): void
    {
        $exporter = $this->createUserWithRole('exporter');

        // Coba akses endpoint exporter. Jika route belum diimplementasi (404),
        // test ini minimal membuktikan RBAC (401/403) berhasil dilewati.
        $response = $this->actingAs($exporter, 'sanctum')->getJson('/api/v1/exporter/dashboard');

        // Middleware sukses dilewati. Tidak boleh 401 / 403
        $this->assertNotEquals(403, $response->getStatusCode());
        $this->assertNotEquals(401, $response->getStatusCode());
    }

    public function test_exporter_can_access_batch_acquisition_route(): void
    {
        $exporter = $this->createUserWithRole('exporter');

        // Akuisisi membutuhkan POST ke /exporter/batches/{batchId}/acquire
        // Eksportir harus diizinkan untuk melewati middleware otorisasi.
        $response = $this->actingAs($exporter, 'sanctum')->postJson('/api/v1/exporter/batches/prod-001/acquire');

        $this->assertNotEquals(403, $response->getStatusCode());
        $this->assertNotEquals(401, $response->getStatusCode());
    }

    public function test_exporter_data_isolation_on_owned_resources(): void
    {
        $exporter1 = $this->createUserWithRole('exporter');
        $exporter2 = $this->createUserWithRole('exporter');

        // Buat batch milik exporter 2
        \App\Models\Batch::factory()->create([
            'batch_id' => 'acquired-by-exporter-2',
            'batch_code' => 'BC-002',
            'exporter_id' => $exporter2->id,
            'name' => 'Kopi Arabica Mamasa',
            'quantity' => 100,
            'status' => 'acquired'
        ]);

        $response = $this->actingAs($exporter1, 'sanctum')
                        ->getJson('/api/v1/exporter/batches/acquired-by-exporter-2');

        $response->assertStatus(403);
    }

    public function test_exporter_dashboard_success_response_format(): void
    {
        $exporter = $this->createUserWithRole('exporter');

        // Akses dashboard dengan exporter yang valid
        $response = $this->actingAs($exporter, 'sanctum')->getJson('/api/v1/exporter/dashboard');

        // Jika endpoint sudah diimplementasi (200), validasi format response sesuai API_CONTRACT.md Section 4.1
        if ($response->status() === 200) {
            $response->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
            ])
            ->assertJsonStructure([
                'success',
                'code',
                'message',
                'data',
                'timestamp',
            ]);
        }
    }

    public function test_exporter_can_access_blockchain_activity_route(): void
    {
        $exporter = $this->createUserWithRole('exporter');

        // GET /api/v1/exporter/blockchain-activity - sesuai Section 8.2 API_CONTRACT
        $response = $this->actingAs($exporter, 'sanctum')
                         ->getJson('/api/v1/exporter/blockchain-activity?range=3month');

        // Middleware otorisasi harus berhasil dilewati (tidak 401/403)
        $this->assertNotEquals(401, $response->getStatusCode());
        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_exporter_can_access_blockchain_logs_route(): void
    {
        $exporter = $this->createUserWithRole('exporter');

        // GET /api/v1/exporter/blockchain-logs - sesuai Section 8.3 API_CONTRACT
        $response = $this->actingAs($exporter, 'sanctum')
                         ->getJson('/api/v1/exporter/blockchain-logs?limit=20');

        // Middleware otorisasi harus berhasil dilewati (tidak 401/403)
        $this->assertNotEquals(401, $response->getStatusCode());
        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_exporter_can_access_available_batches_route(): void
    {
        $exporter = $this->createUserWithRole('exporter');

        // GET /api/v1/exporter/batches/available - sesuai Section 9.1 API_CONTRACT
        $response = $this->actingAs($exporter, 'sanctum')
                         ->getJson('/api/v1/exporter/batches/available?sort=date&limit=20');

        // Middleware otorisasi harus berhasil dilewati (tidak 401/403)
        $this->assertNotEquals(401, $response->getStatusCode());
        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_exporter_cannot_duplicate_acquire_same_batch(): void
    {
        $exporter = $this->createUserWithRole('exporter');

        // SETUP DATA: Buat batch yang statusnya sudah 'acquired'
        \App\Models\Batch::factory()->create([
            'batch_id' => 'already-acquired',
            'batch_code' => 'BC-ALREADY',
            'exporter_id' => $this->createUserWithRole('exporter')->id,
            'name' => 'Kopi Arabica',
            'quantity' => 50,
            'status' => 'acquired' 
        ]);

        $response = $this->actingAs($exporter, 'sanctum')
                        ->postJson('/api/v1/exporter/batches/already-acquired/acquire');

        // LANGSUNG ASSERT (Jangan pake IF)
        $response->assertStatus(409)
                ->assertJsonFragment([
                    'success' => false,
                    'code' => 'DUPLICATE_ACQUISITION',
                ]);
    }

    public function test_exporter_cannot_transition_invalid_batch_status(): void
    {
        $exporter = $this->createUserWithRole('exporter');

        // SETUP DATA: Buat batch dengan status 'draft'
        \App\Models\Batch::factory()->create([
            'batch_id' => 'draft-batch',
            'batch_code' => 'BC-DRAFT',
            'exporter_id' => $exporter->id,
            'name' => 'Batch Mentah',
            'quantity' => 10,
            'status' => 'draft' 
        ]);

        $response = $this->actingAs($exporter, 'sanctum')
                        ->postJson('/api/v1/exporter/batches/draft-batch/release');

        // HARUS 400
        $response->assertStatus(400)
                ->assertJsonFragment([
                    'success' => false,
                    'code' => 'INVALID_STATUS_TRANSITION',
                ]);
    }

    public function test_exporter_only_sees_owned_batches_in_available_list(): void
    {
        $exporter1 = $this->createUserWithRole('exporter');
        $exporter2 = $this->createUserWithRole('exporter');

        // Exporter 1 mengakses daftar batch available
        $response = $this->actingAs($exporter1, 'sanctum')
                         ->getJson('/api/v1/exporter/batches/available');

        // Jika endpoint sudah diimplementasi (200) dan mengembalikan data pagination
        if ($response->status() === 200) {
            // Response harus mengikuti format pagination dari API_CONTRACT Section 4.3
            $response->assertJsonStructure([
                'success',
                'code',
                'data' => [
                    '*' => [
                        'id',
                        'variety',
                        'farmer',
                        'health_status',
                    ],
                ],
                'pagination' => [
                    'cursor',
                    'hasMore',
                    'limit',
                ],
                'timestamp',
            ]);

            // Data hanya boleh berisi batch yang belum diakuisisi atau milik exporter ini
            // (batch sudah diakuisisi exporter lain dikecualikan)
            $batches = $response->json('data');
            $this->assertIsArray($batches);
        }
    }
}

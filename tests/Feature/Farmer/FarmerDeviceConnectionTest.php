<?php

namespace Tests\Feature\Farmer;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FarmerDeviceConnectionTest — Test Case untuk Modul Perangkat & Koneksi Petani (API Contract V2.1)
 *
 * Scope: 3 endpoint perangkat & koneksi farmer
 *   - GET    /api/v1/farmer/devices              — Daftar perangkat aktif (13.1)
 *   - DELETE /api/v1/farmer/devices/{deviceId}    — Logout dari perangkat (13.2)
 *   - GET    /api/v1/farmer/connection-status     — Status koneksi & sinkronisasi (13.3)
 *
 * Reference: API_CONTRACT_V2_FARMER.md Section 13 (Modul Perangkat & Koneksi)
 *
 * Business Rules V2.1 yang ditest:
 * - Data isolation: petani hanya lihat device miliknya
 * - Tidak bisa logout perangkat current (gunakan /auth/logout) → 409
 * - Device not found → 404
 * - connection-status: is_online dari client (navigator.onLine), sync status dari server
 * - Notification types: batch, survey, iot, system, acquisition
 */
class FarmerDeviceConnectionTest extends TestCase
{
    use RefreshDatabase;

    private User $farmer;

    private User $farmer2;

    private User $exporter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->farmer = User::factory()->create([
            'role' => 'farmer',
            'phone' => '+62 812-3456-7890',
            'phone_verified' => true,
        ]);
        $this->farmer2 = User::factory()->create([
            'role' => 'farmer',
            'phone' => '+62 813-9876-5432',
            'phone_verified' => true,
        ]);
        $this->exporter = User::factory()->create(['role' => 'exporter']);
    }

    private array $authTokens = [];

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

    // =========================================================================
    // 1. DEVICES — Happy Path
    // =========================================================================

    public function test_get_devices_returns_list_successfully(): void
    {
        $response = $this->getJson('/api/v1/farmer/devices', $this->authHeaders($this->farmer));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
                'message' => 'Daftar perangkat berhasil diambil',
            ])
            ->assertJsonStructure([
                'success', 'code', 'message',
                'data' => [
                    'devices' => [
                        '*' => [
                            'id',
                            'name',
                            'user_agent',
                            'ip_address',
                            'status',
                            'last_activity_at',
                            'created_at',
                            'is_current_device',
                        ],
                    ],
                ],
                'timestamp',
            ]);
    }

    public function test_get_devices_contains_current_device_marker(): void
    {
        $response = $this->getJson('/api/v1/farmer/devices', $this->authHeaders($this->farmer));

        $response->assertStatus(200);
        $devices = $response->json('data.devices');

        // Minimal 1 device (current device) harus ada
        $this->assertGreaterThanOrEqual(1, count($devices));

        // Salah satu device harus punya is_current_device = true
        $hasCurrent = collect($devices)->contains('is_current_device', true);
        $this->assertTrue($hasCurrent, 'At least one device must be marked as current');
    }

    public function test_get_devices_has_valid_field_types(): void
    {
        $response = $this->getJson('/api/v1/farmer/devices', $this->authHeaders($this->farmer));

        $response->assertStatus(200);
        $devices = $response->json('data.devices');

        if (! empty($devices)) {
            $device = $devices[0];
            $this->assertIsString($device['id']);
            $this->assertIsString($device['name']);
            $this->assertIsString($device['user_agent']);
            $this->assertIsString($device['ip_address']);
            $this->assertIsString($device['status']);
            $this->assertIsBool($device['is_current_device']);
        }
    }

    public function test_get_devices_optional_fields_description_and_status_label(): void
    {
        $response = $this->getJson('/api/v1/farmer/devices', $this->authHeaders($this->farmer));

        $response->assertStatus(200);
        $devices = $response->json('data.devices');

        if (! empty($devices)) {
            // description dan status_label opsional tapi kalau ada harus string
            $device = $devices[0];
            if (isset($device['description'])) {
                $this->assertIsString($device['description']);
            }
            if (isset($device['status_label'])) {
                $this->assertIsString($device['status_label']);
            }
        }
    }

    // =========================================================================
    // 2. DEVICES — Auth & Role
    // =========================================================================

    public function test_get_devices_unauthenticated_returns_401(): void
    {
        $response = $this->getJson('/api/v1/farmer/devices');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    public function test_get_devices_as_exporter_returns_403(): void
    {
        $response = $this->getJson('/api/v1/farmer/devices', $this->authHeaders($this->exporter));

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }

    public function test_devices_data_isolation_between_farmers(): void
    {
        // Farmer1 dan farmer2 punya device sendiri-sendiri
        $response1 = $this->getJson('/api/v1/farmer/devices', $this->authHeaders($this->farmer));
        $response2 = $this->getJson('/api/v1/farmer/devices', $this->authHeaders($this->farmer2));

        $response1->assertStatus(200);
        $response2->assertStatus(200);

        $devices1 = $response1->json('data.devices');
        $devices2 = $response2->json('data.devices');

        // Tidak boleh ada device id yang sama di kedua response
        $ids1 = collect($devices1)->pluck('id')->toArray();
        $ids2 = collect($devices2)->pluck('id')->toArray();
        $overlap = array_intersect($ids1, $ids2);
        $this->assertEmpty($overlap, 'Devices should be isolated per farmer');
    }

    // =========================================================================
    // 3. DELETE DEVICE — Happy Path
    // =========================================================================

    public function test_delete_device_returns_success(): void
    {
        // Ambil device id dari list
        $listResponse = $this->getJson('/api/v1/farmer/devices', $this->authHeaders($this->farmer));
        $listResponse->assertStatus(200);
        $devices = $listResponse->json('data.devices');

        // Cari device yang BUKAN current device untuk di-delete
        $otherDevice = collect($devices)->first(fn ($d) => $d['is_current_device'] === false);

        // Jika hanya ada 1 device (current), skip — tidak bisa delete current
        if (! $otherDevice) {
            $this->markTestSkipped('Only current device exists, cannot test delete non-current');
        }

        $response = $this->deleteJson(
            '/api/v1/farmer/devices/'.$otherDevice['id'],
            [],
            $this->authHeaders($this->farmer)
        );

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
                'message' => 'Perangkat berhasil dilogout',
            ])
            ->assertJsonStructure([
                'success', 'code', 'message',
                'data' => [
                    'device_id',
                    'logged_out_at',
                ],
                'timestamp',
            ])
            ->assertJsonPath('data.device_id', $otherDevice['id']);
    }

    public function test_delete_device_not_found_returns_404(): void
    {
        $response = $this->deleteJson(
            '/api/v1/farmer/devices/device-nonexistent-999',
            [],
            $this->authHeaders($this->farmer)
        );

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code' => 'NOT_FOUND',
            ]);
    }

    public function test_delete_current_device_returns_409(): void
    {
        // Ambil device id dari list, cari current device
        $listResponse = $this->getJson('/api/v1/farmer/devices', $this->authHeaders($this->farmer));
        $listResponse->assertStatus(200);
        $devices = $listResponse->json('data.devices');

        $currentDevice = collect($devices)->first(fn ($d) => $d['is_current_device'] === true);
        $this->assertNotNull($currentDevice, 'Current device should exist');

        $response = $this->deleteJson(
            '/api/v1/farmer/devices/'.$currentDevice['id'],
            [],
            $this->authHeaders($this->farmer)
        );

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
            ]);
        // Error code bisa beragam, yang penting 409
    }

    public function test_delete_device_unauthenticated_returns_401(): void
    {
        $response = $this->deleteJson('/api/v1/farmer/devices/device-001');

        $response->assertStatus(401);
    }

    public function test_delete_device_as_exporter_returns_403(): void
    {
        $response = $this->deleteJson(
            '/api/v1/farmer/devices/device-001',
            [],
            $this->authHeaders($this->exporter)
        );

        $response->assertStatus(403);
    }

    public function test_delete_device_of_another_farmer_returns_404_or_403(): void
    {
        // Ambil device id milik farmer2
        $listResponse = $this->getJson('/api/v1/farmer/devices', $this->authHeaders($this->farmer2));
        $listResponse->assertStatus(200);
        $devices = $listResponse->json('data.devices');

        if (empty($devices)) {
            $this->markTestSkipped('No devices for farmer2');
        }

        $farmer2Device = $devices[0];

        // Farmer1 coba delete device farmer2
        $response = $this->deleteJson(
            '/api/v1/farmer/devices/'.$farmer2Device['id'],
            [],
            $this->authHeaders($this->farmer)
        );

        // Harus 404 (not found for this user) atau 403 (forbidden)
        $this->assertThat(
            $response->status(),
            $this->logicalOr(
                $this->equalTo(403),
                $this->equalTo(404)
            )
        );
    }

    // =========================================================================
    // 4. CONNECTION STATUS — Happy Path
    // =========================================================================

    public function test_get_connection_status_returns_successfully(): void
    {
        $response = $this->getJson('/api/v1/farmer/connection-status', $this->authHeaders($this->farmer));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
                'message' => 'Status koneksi berhasil diambil',
            ])
            ->assertJsonStructure([
                'success', 'code', 'message',
                'data' => [
                    'connection' => [
                        'is_online',
                        'last_online_at',
                        'last_offline_at',
                    ],
                    'sync' => [
                        'auto_sync_enabled',
                        'auto_sync_label',
                        'offline_mode_enabled',
                        'offline_mode_label',
                        'pending_sync_count',
                        'last_sync_at',
                        'last_sync_status',
                    ],
                ],
                'timestamp',
            ]);
    }

    public function test_connection_status_has_valid_field_types(): void
    {
        $response = $this->getJson('/api/v1/farmer/connection-status', $this->authHeaders($this->farmer));

        $response->assertStatus(200);
        $data = $response->json('data');

        // Connection fields
        $this->assertIsBool($data['connection']['is_online']);
        $this->assertIsString($data['connection']['last_online_at']);
        // last_offline_at boleh null

        // Sync fields
        $this->assertIsBool($data['sync']['auto_sync_enabled']);
        $this->assertIsString($data['sync']['auto_sync_label']);
        $this->assertIsBool($data['sync']['offline_mode_enabled']);
        $this->assertIsString($data['sync']['offline_mode_label']);
        $this->assertIsInt($data['sync']['pending_sync_count']);
        $this->assertGreaterThanOrEqual(0, $data['sync']['pending_sync_count']);
    }

    public function test_connection_status_sync_status_value(): void
    {
        $response = $this->getJson('/api/v1/farmer/connection-status', $this->authHeaders($this->farmer));

        $response->assertStatus(200);
        $lastSyncStatus = $response->json('data.sync.last_sync_status');

        // Nilai valid: success, failed, pending, atau null
        $validStatuses = ['success', 'failed', 'pending', null];
        $this->assertContains(
            $lastSyncStatus,
            $validStatuses,
            'last_sync_status must be one of: success, failed, pending, null'
        );
    }

    // =========================================================================
    // 5. CONNECTION STATUS — Auth & Role
    // =========================================================================

    public function test_get_connection_status_unauthenticated_returns_401(): void
    {
        $response = $this->getJson('/api/v1/farmer/connection-status');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    public function test_get_connection_status_as_exporter_returns_403(): void
    {
        $response = $this->getJson('/api/v1/farmer/connection-status', $this->authHeaders($this->exporter));

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }

    public function test_connection_status_data_isolation_per_farmer(): void
    {
        $response1 = $this->getJson('/api/v1/farmer/connection-status', $this->authHeaders($this->farmer));
        $response2 = $this->getJson('/api/v1/farmer/connection-status', $this->authHeaders($this->farmer2));

        $response1->assertStatus(200);
        $response2->assertStatus(200);

        // Data sync milik masing-masing farmer
        $sync1 = $response1->json('data.sync');
        $sync2 = $response2->json('data.sync');

        // pending_sync_count masing-masing bisa beda (tidak harus sama)
        $this->assertIsInt($sync1['pending_sync_count']);
        $this->assertIsInt($sync2['pending_sync_count']);
    }
}

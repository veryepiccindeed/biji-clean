<?php

namespace Tests\Feature\Buyer;

use App\Models\Port;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * BuyerPortTest — Test Case untuk Modul Pelabuhan (Ports) Buyer (API Contract V3)
 *
 * Scope: 1 endpoint utama
 *   - GET /api/v1/buyer/ports — Daftar pelabuhan pickup aktif (§8.1)
 *
 * Reference: API_CONTRACT_V3_BUYER.md
 *   - Section 8  (Modul Pelabuhan — Ports)
 *   - Section 5  (Kode Error Shared + Buyer-Specific)
 *   - Section 16 (Edge Cases & Logika Bisnis Buyer)
 *
 * Response fields yang ditest:
 *   - port.id, port.name, port.full_name
 *   - port.country, port.city
 *   - port.eta_days, port.eta_label
 *   - port.shipping_rate_per_kg
 *   - port.is_active
 *   - port.description
 *
 * Business Rules V3 yang ditest:
 *   - Hanya port dengan is_active = true yang dikembalikan
 *   - shipping_rate_per_kg digunakan untuk kalkulasi checkout
 *   - eta_days adalah estimasi pengiriman dari gudang exporter ke pelabuhan
 *   - Data pelabuhan dikelola admin/system (read-only untuk buyer)
 *   - Tidak ada query parameters — endpoint mengembalikan semua port aktif
 *   - Endpoint ini digunakan di Checkout.vue (dropdown port tujuan)
 *
 * Sections (24 tests):
 *   1.  Auth & Authorization (4 tests)
 *   2.  Response Structure & Fields (4 tests)
 *   3.  Data Filtering — Active Ports Only (3 tests)
 *   4.  Response Data Integrity (5 tests)
 *   5.  Business Logic — Shipping & ETA (4 tests)
 *   6.  Edge Cases (4 tests)
 */
class BuyerPortTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private User $buyer2;

    private User $exporter;

    private User $farmer;

    protected function setUp(): void
    {
        parent::setUp();

        // ── Buyer utama ──
        $this->buyer = User::factory()->create([
            'role' => 'buyer',
            'name' => 'John Doe',
            'email' => 'john@company.com',
            'phone' => '+62 812-0000-0001',
            'company_name' => 'PT Kopi Nusantara',
            'business_id' => 'NPWP-1234567890',
            'profile_completion' => 80,
        ]);

        // ── Buyer kedua (untuk isolasi test) ──
        $this->buyer2 = User::factory()->create([
            'role' => 'buyer',
            'name' => 'Jane Smith',
            'email' => 'jane@coffee.co',
            'phone' => '+62 813-0000-0002',
            'company_name' => 'Pacific Roasters Inc.',
            'business_id' => 'NPWP-9876543210',
            'profile_completion' => 60,
        ]);

        // ── Exporter ──
        $this->exporter = User::factory()->create([
            'role' => 'exporter',
            'name' => 'PT Sulawesi Coffee Export',
            'email' => 'export@sulawesi-coffee.id',
        ]);

        // ── Farmer ──
        $this->farmer = User::factory()->create([
            'role' => 'farmer',
            'name' => 'Yusuf Ibrahim',
        ]);

        // ── Seeder ports default ──
        $this->seedDefaultPorts();
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    /**
     * Seed port data default (3 aktif, 1 inactive)
     */
    private function seedDefaultPorts(): void
    {
        Port::factory()->create([
            'id' => 1,
            'name' => 'Tanjung Priok',
            'full_name' => 'Pelabuhan Tanjung Priok, Jakarta',
            'country' => 'Indonesia',
            'city' => 'Jakarta',
            'eta_days' => 3,
            'eta_label' => 'Estimasi 2-3 hari',
            'shipping_rate_per_kg' => 2500,
            'is_active' => true,
            'description' => 'Pelabuhan utama Jakarta, melayani pengiriman kopi dari Sulawesi dan Sumatera',
        ]);

        Port::factory()->create([
            'id' => 2,
            'name' => 'Tanjung Emas',
            'full_name' => 'Pelabuhan Tanjung Emas, Semarang',
            'country' => 'Indonesia',
            'city' => 'Semarang',
            'eta_days' => 5,
            'eta_label' => 'Estimasi 4-5 hari',
            'shipping_rate_per_kg' => 3000,
            'is_active' => true,
            'description' => 'Pelabuhan Semarang, alternatif untuk pengiriman ke Jawa Tengah',
        ]);

        Port::factory()->create([
            'id' => 3,
            'name' => 'Belawan',
            'full_name' => 'Pelabuhan Belawan, Medan',
            'country' => 'Indonesia',
            'city' => 'Medan',
            'eta_days' => 7,
            'eta_label' => 'Estimasi 6-7 hari',
            'shipping_rate_per_kg' => 3500,
            'is_active' => true,
            'description' => 'Pelabuhan Medan, melayani pengiriman ke Sumatera Utara',
        ]);

        // Port inactive (tidak boleh muncul di response)
        Port::factory()->create([
            'id' => 4,
            'name' => 'Makassar Port',
            'full_name' => 'Pelabuhan Makassar, Sulawesi Selatan',
            'country' => 'Indonesia',
            'city' => 'Makassar',
            'eta_days' => 1,
            'eta_label' => 'Estimasi 1 hari',
            'shipping_rate_per_kg' => 1500,
            'is_active' => false,
            'description' => 'Pelabuhan Makassar — saat ini tidak tersedia untuk pickup',
        ]);
    }

    /**
     * Helper: Panggil endpoint ports sebagai user tertentu
     */
    private function getPorts(?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->buyer)
            ->getJson('/api/v1/buyer/ports');
    }

    /**
     * Helper: Buat port dengan status tertentu
     */
    private function createPort(int $id, bool $isActive, array $overrides = []): Port
    {
        return Port::factory()->create(array_merge([
            'id' => $id,
            'name' => "Port {$id}",
            'full_name' => "Pelabuhan Port {$id}, Indonesia",
            'country' => 'Indonesia',
            'city' => "Kota {$id}",
            'eta_days' => 3,
            'eta_label' => 'Estimasi 2-3 hari',
            'shipping_rate_per_kg' => 2500,
            'is_active' => $isActive,
            'description' => "Deskripsi pelabuhan {$id}",
        ], $overrides));
    }

    // ========================================================================
    // SECTION 1: AUTH & AUTHORIZATION (4 tests)
    // ========================================================================

    /** @test */
    public function test_it_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/buyer/ports');

        $response->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    /** @test */
    public function test_it_rejects_farmer_role(): void
    {
        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/buyer/ports');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }

    /** @test */
    public function test_it_rejects_exporter_role(): void
    {
        $response = $this->actingAs($this->exporter)
            ->getJson('/api/v1/buyer/ports');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }

    /** @test */
    public function test_it_allows_buyer_role(): void
    {
        $response = $this->getPorts();

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
                'message' => 'Daftar pelabuhan berhasil diambil',
            ]);
    }

    // ========================================================================
    // SECTION 2: RESPONSE STRUCTURE & FIELDS (4 tests)
    // ========================================================================

    /** @test */
    public function test_it_returns_200_with_success_format(): void
    {
        $response = $this->getPorts();

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'code',
                'message',
                'data',
                'timestamp',
            ])
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
            ]);
    }

    /** @test */
    public function test_it_returns_array_of_port_objects(): void
    {
        $response = $this->getPorts();

        $response->assertOk();

        $ports = $response->json('data');
        $this->assertIsArray($ports);
        $this->assertNotEmpty($ports);

        // Setiap item harus berupa array/object
        foreach ($ports as $port) {
            $this->assertIsArray($port);
        }
    }

    /** @test */
    public function test_each_port_has_all_required_fields(): void
    {
        $response = $this->getPorts();

        $response->assertOk();

        $ports = $response->json('data');
        $this->assertNotEmpty($ports);

        $requiredFields = [
            'id',
            'name',
            'full_name',
            'country',
            'city',
            'eta_days',
            'eta_label',
            'shipping_rate_per_kg',
            'is_active',
            'description',
        ];

        foreach ($ports as $port) {
            foreach ($requiredFields as $field) {
                $this->assertArrayHasKey($field, $port, "Field '{$field}' harus ada di setiap port");
            }
        }
    }

    /** @test */
    public function test_it_returns_data_without_pagination_wrapper(): void
    {
        // Endpoint ports mengembalikan array langsung, bukan paginated response
        $response = $this->getPorts();

        $response->assertOk();

        // Tidak ada pagination wrapper
        $this->assertArrayNotHasKey('pagination', $response->json());

        // Data langsung berupa array ports
        $this->assertIsArray($response->json('data'));
    }

    // ========================================================================
    // SECTION 3: DATA FILTERING — ACTIVE PORTS ONLY (3 tests)
    // ========================================================================

    /** @test */
    public function test_it_only_returns_active_ports(): void
    {
        $response = $this->getPorts();

        $response->assertOk();

        $ports = $response->json('data');

        // Semua port yang dikembalikan harus is_active = true
        foreach ($ports as $port) {
            $this->assertTrue($port['is_active'], "Port {$port['name']} harus aktif");
        }
    }

    /** @test */
    public function test_it_excludes_inactive_ports(): void
    {
        $response = $this->getPorts();

        $response->assertOk();

        $portNames = array_column($response->json('data'), 'name');

        // Port aktif harus ada
        $this->assertContains('Tanjung Priok', $portNames);
        $this->assertContains('Tanjung Emas', $portNames);
        $this->assertContains('Belawan', $portNames);

        // Port inactive TIDAK boleh ada
        $this->assertNotContains('Makassar Port', $portNames, 'Port inactive tidak boleh muncul');
    }

    /** @test */
    public function test_it_returns_empty_array_when_no_active_ports_exist(): void
    {
        // Hapus semua port aktif, hanya sisakan port inactive
        Port::where('is_active', true)->delete();

        $response = $this->getPorts();

        $response->assertOk()
            ->assertJsonPath('data', []);
    }

    // ========================================================================
    // SECTION 4: RESPONSE DATA INTEGRITY (5 tests)
    // ========================================================================

    /** @test */
    public function test_it_returns_correct_port_data_for_tanjung_priok(): void
    {
        $response = $this->getPorts();

        $response->assertOk();

        $ports = $response->json('data');
        $tanjungPriok = collect($ports)->firstWhere('id', 1);

        $this->assertNotNull($tanjungPriok);
        $this->assertEquals('Tanjung Priok', $tanjungPriok['name']);
        $this->assertEquals('Pelabuhan Tanjung Priok, Jakarta', $tanjungPriok['full_name']);
        $this->assertEquals('Indonesia', $tanjungPriok['country']);
        $this->assertEquals('Jakarta', $tanjungPriok['city']);
        $this->assertEquals(3, $tanjungPriok['eta_days']);
        $this->assertEquals('Estimasi 2-3 hari', $tanjungPriok['eta_label']);
        $this->assertEquals(2500, $tanjungPriok['shipping_rate_per_kg']);
        $this->assertEquals(true, $tanjungPriok['is_active']);
        $this->assertStringContainsString('Jakarta', $tanjungPriok['description']);
    }

    /** @test */
    public function test_it_returns_correct_port_data_for_tanjung_emas(): void
    {
        $response = $this->getPorts();

        $response->assertOk();

        $ports = $response->json('data');
        $tanjungEmas = collect($ports)->firstWhere('id', 2);

        $this->assertNotNull($tanjungEmas);
        $this->assertEquals('Tanjung Emas', $tanjungEmas['name']);
        $this->assertEquals('Pelabuhan Tanjung Emas, Semarang', $tanjungEmas['full_name']);
        $this->assertEquals('Semarang', $tanjungEmas['city']);
        $this->assertEquals(5, $tanjungEmas['eta_days']);
        $this->assertEquals(3000, $tanjungEmas['shipping_rate_per_kg']);
    }

    /** @test */
    public function test_it_returns_correct_port_data_for_belawan(): void
    {
        $response = $this->getPorts();

        $response->assertOk();

        $ports = $response->json('data');
        $belawan = collect($ports)->firstWhere('id', 3);

        $this->assertNotNull($belawan);
        $this->assertEquals('Belawan', $belawan['name']);
        $this->assertEquals('Pelabuhan Belawan, Medan', $belawan['full_name']);
        $this->assertEquals('Medan', $belawan['city']);
        $this->assertEquals(7, $belawan['eta_days']);
        $this->assertEquals(3500, $belawan['shipping_rate_per_kg']);
    }

    /** @test */
    public function test_it_returns_all_three_active_ports(): void
    {
        $response = $this->getPorts();

        $response->assertOk();

        $ports = $response->json('data');

        // Harus ada tepat 3 port aktif
        $this->assertCount(3, $ports, 'Harus ada tepat 3 port aktif');

        // Verifikasi ID semua port aktif
        $portIds = array_column($ports, 'id');
        $this->assertEqualsCanonicalizing([1, 2, 3], $portIds);
    }

    /** @test */
    public function test_port_ids_are_integers(): void
    {
        $response = $this->getPorts();

        $response->assertOk();

        $ports = $response->json('data');

        foreach ($ports as $port) {
            $this->assertIsInt($port['id']);
        }
    }

    // ========================================================================
    // SECTION 5: BUSINESS LOGIC — SHIPPING & ETA (4 tests)
    // ========================================================================

    /** @test */
    public function test_each_port_has_positive_shipping_rate(): void
    {
        $response = $this->getPorts();

        $response->assertOk();

        $ports = $response->json('data');

        foreach ($ports as $port) {
            $this->assertGreaterThan(0, $port['shipping_rate_per_kg'],
                "Port {$port['name']} harus memiliki shipping_rate_per_kg > 0"
            );
        }
    }

    /** @test */
    public function test_each_port_has_positive_eta_days(): void
    {
        $response = $this->getPorts();

        $response->assertOk();

        $ports = $response->json('data');

        foreach ($ports as $port) {
            $this->assertGreaterThan(0, $port['eta_days'],
                "Port {$port['name']} harus memiliki eta_days > 0"
            );
        }
    }

    /** @test */
    public function test_shipping_rates_are_consistent_with_expected_values(): void
    {
        $response = $this->getPorts();

        $response->assertOk();

        $ports = $response->json('data');

        $rateMap = [
            1 => 2500,  // Tanjung Priok
            2 => 3000,  // Tanjung Emas
            3 => 3500,  // Belawan
        ];

        foreach ($ports as $port) {
            $expectedRate = $rateMap[$port['id']] ?? null;
            if ($expectedRate !== null) {
                $this->assertEquals($expectedRate, $port['shipping_rate_per_kg'],
                    "Shipping rate untuk port {$port['name']} tidak sesuai"
                );
            }
        }
    }

    /** @test */
    public function test_eta_labels_contain_eta_days_reference(): void
    {
        $response = $this->getPorts();

        $response->assertOk();

        $ports = $response->json('data');

        foreach ($ports as $port) {
            // eta_label harus mengandung referensi hari
            $this->assertNotEmpty($port['eta_label']);
            $this->assertStringContainsString('hari', $port['eta_label'],
                "eta_label port {$port['name']} harus mengandung kata 'hari'"
            );
        }
    }

    // ========================================================================
    // SECTION 6: EDGE CASES (4 tests)
    // ========================================================================

    /** @test */
    public function test_it_returns_same_ports_for_any_buyer(): void
    {
        // Data ports adalah global (bukan per-buyer), jadi buyer1 dan buyer2
        // harus melihat data yang identik

        $response1 = $this->getPorts($this->buyer);
        $response1->assertOk();
        $ports1 = $response1->json('data');

        $response2 = $this->getPorts($this->buyer2);
        $response2->assertOk();
        $ports2 = $response2->json('data');

        // Jumlah port harus sama
        $this->assertCount(count($ports1), $ports2);

        // ID port harus sama
        $ids1 = array_column($ports1, 'id');
        $ids2 = array_column($ports2, 'id');
        $this->assertEqualsCanonicalizing($ids1, $ids2);
    }

    /** @test */
    public function test_newly_activated_port_appears_in_response(): void
    {
        // Buat port baru yang aktif
        $this->createPort(99, true, [
            'name' => 'Port Baru Aktif',
            'full_name' => 'Pelabuhan Baru Aktif, Surabaya',
            'city' => 'Surabaya',
        ]);

        $response = $this->getPorts();

        $response->assertOk();

        $portNames = array_column($response->json('data'), 'name');
        $this->assertContains('Port Baru Aktif', $portNames);

        // Total port = 4 (3 default + 1 baru)
        $this->assertCount(4, $response->json('data'));
    }

    /** @test */
    public function test_newly_deactivated_port_disappears_from_response(): void
    {
        // Deaktifkan Tanjung Priok
        Port::where('id', 1)->update(['is_active' => false]);

        $response = $this->getPorts();

        $response->assertOk();

        $portNames = array_column($response->json('data'), 'name');

        // Tanjung Priok tidak boleh muncul
        $this->assertNotContains('Tanjung Priok', $portNames);

        // Sisa 2 port aktif
        $this->assertCount(2, $response->json('data'));
    }

    /** @test */
    public function test_port_data_is_read_only_no_modification_endpoint(): void
    {
        // Buyer tidak bisa POST/PUT/PATCH/DELETE ports
        $this->actingAs($this->buyer)
            ->postJson('/api/v1/buyer/ports', [])
            ->assertStatus(405); // Method Not Allowed

        $this->actingAs($this->buyer)
            ->putJson('/api/v1/buyer/ports/1', [])
            ->assertStatus(405);

        $this->actingAs($this->buyer)
            ->patchJson('/api/v1/buyer/ports/1', [])
            ->assertStatus(405);

        $this->actingAs($this->buyer)
            ->deleteJson('/api/v1/buyer/ports/1')
            ->assertStatus(405);
    }
}

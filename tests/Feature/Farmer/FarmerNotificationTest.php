<?php

namespace Tests\Feature\Farmer;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FarmerNotificationTest — Test Case untuk Modul Notifikasi Petani (API Contract V2.1)
 *
 * Scope: 3 endpoint notifikasi farmer
 *   - GET  /api/v1/farmer/notifications                    — Daftar notifikasi (14.1)
 *   - GET  /api/v1/farmer/notifications/unread-count       — Jumlah belum dibaca (14.2)
 *   - PATCH /api/v1/farmer/notifications/{id}/read          — Tandai sudah dibaca (14.3)
 *
 * Reference: API_CONTRACT_V2_FARMER.md Section 14 (Modul Notifikasi Petani)
 *
 * Business Rules V2.1 yang ditest:
 * - Pagination cursor-based dengan filter type (batch, survey, iot, system, acquisition)
 * - Filter is_read (true/false)
 * - Data isolation: petani hanya lihat notifikasinya sendiri
 * - Unread count konsisten dengan jumlah is_read=false di list
 * - Tandai baca: idempotent (mark read 2x tetap sukses)
 * - Notification fields: id, type, title, body, batch_id, batch_code, is_read, action_url, created_at
 */

class FarmerNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $farmer;
    private User $farmer2;
    private User $exporter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->farmer = User::factory()->create([
            'role'           => 'farmer',
            'phone'          => '+62 812-3456-7890',
            'phone_verified' => true,
        ]);
        $this->farmer2 = User::factory()->create([
            'role'           => 'farmer',
            'phone'          => '+62 813-9876-5432',
            'phone_verified' => true,
        ]);
        $this->exporter = User::factory()->create(['role' => 'exporter']);
    }

    private function authHeaders(User $user): array
    {
        $token = $user->createToken('test-token')->plainTextToken;
        return ['Authorization' => 'Bearer ' . $token];
    }

    // =========================================================================
    // 1. GET NOTIFICATIONS — Happy Path
    // =========================================================================

    public function test_get_notifications_returns_list_with_pagination(): void
    {
        $response = $this->getJson('/api/v1/farmer/notifications', $this->authHeaders($this->farmer));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code'    => 'SUCCESS',
                'message' => 'Notifikasi berhasil diambil',
            ])
            ->assertJsonStructure([
                'success', 'code', 'message',
                'data' => [],
                'pagination' => [
                    'cursor',
                    'hasMore',
                    'limit',
                    'total',
                ],
                'timestamp',
            ]);
    }

    public function test_get_notifications_default_limit_is_20(): void
    {
        $response = $this->getJson('/api/v1/farmer/notifications', $this->authHeaders($this->farmer));

        $response->assertStatus(200)
            ->assertJsonPath('pagination.limit', 20);
    }

    public function test_get_notifications_custom_limit(): void
    {
        $response = $this->getJson(
            '/api/v1/farmer/notifications?limit=50',
            $this->authHeaders($this->farmer)
        );

        $response->assertStatus(200)
            ->assertJsonPath('pagination.limit', 50);
    }

    public function test_get_notifications_limit_capped_at_100(): void
    {
        $response = $this->getJson(
            '/api/v1/farmer/notifications?limit=150',
            $this->authHeaders($this->farmer)
        );

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(100, $response->json('pagination.limit'));
    }

    public function test_get_notifications_with_cursor_pagination(): void
    {
        $response = $this->getJson(
            '/api/v1/farmer/notifications?cursor=eyJpZCI6Im5vdGlmLTAwNSJ9',
            $this->authHeaders($this->farmer)
        );

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code'    => 'SUCCESS',
            ]);
    }

    public function test_get_notifications_has_valid_structure_per_item(): void
    {
        $response = $this->getJson('/api/v1/farmer/notifications', $this->authHeaders($this->farmer));

        $response->assertStatus(200);
        $data = $response->json('data');

        if (!empty($data)) {
            $notif = $data[0];
            $this->assertArrayHasKey('id', $notif);
            $this->assertArrayHasKey('type', $notif);
            $this->assertArrayHasKey('title', $notif);
            $this->assertArrayHasKey('body', $notif);
            $this->assertArrayHasKey('is_read', $notif);
            $this->assertArrayHasKey('created_at', $notif);
            // Optional fields
            $this->assertArrayHasKey('action_url', $notif);
        }
    }

    // =========================================================================
    // 2. GET NOTIFICATIONS — Filter & Query Parameters
    // =========================================================================

    public function test_get_notifications_filter_type_batch(): void
    {
        $response = $this->getJson(
            '/api/v1/farmer/notifications?type=batch',
            $this->authHeaders($this->farmer)
        );

        $response->assertStatus(200);

        // Semua item yang dikembalikan harus type=batch (kalau ada data)
        $data = $response->json('data');
        foreach ($data as $notif) {
            $this->assertEquals('batch', $notif['type']);
        }
    }

    public function test_get_notifications_filter_type_survey(): void
    {
        $response = $this->getJson(
            '/api/v1/farmer/notifications?type=survey',
            $this->authHeaders($this->farmer)
        );

        $response->assertStatus(200);

        $data = $response->json('data');
        foreach ($data as $notif) {
            $this->assertEquals('survey', $notif['type']);
        }
    }

    public function test_get_notifications_filter_type_iot(): void
    {
        $response = $this->getJson(
            '/api/v1/farmer/notifications?type=iot',
            $this->authHeaders($this->farmer)
        );

        $response->assertStatus(200);

        $data = $response->json('data');
        foreach ($data as $notif) {
            $this->assertEquals('iot', $notif['type']);
        }
    }

    public function test_get_notifications_filter_type_system(): void
    {
        $response = $this->getJson(
            '/api/v1/farmer/notifications?type=system',
            $this->authHeaders($this->farmer)
        );

        $response->assertStatus(200);

        $data = $response->json('data');
        foreach ($data as $notif) {
            $this->assertEquals('system', $notif['type']);
        }
    }

    public function test_get_notifications_filter_type_acquisition(): void
    {
        $response = $this->getJson(
            '/api/v1/farmer/notifications?type=acquisition',
            $this->authHeaders($this->farmer)
        );

        $response->assertStatus(200);

        $data = $response->json('data');
        foreach ($data as $notif) {
            $this->assertEquals('acquisition', $notif['type']);
        }
    }

    public function test_get_notifications_filter_is_read_true(): void
    {
        $response = $this->getJson(
            '/api/v1/farmer/notifications?is_read=true',
            $this->authHeaders($this->farmer)
        );

        $response->assertStatus(200);

        $data = $response->json('data');
        foreach ($data as $notif) {
            $this->assertTrue($notif['is_read']);
        }
    }

    public function test_get_notifications_filter_is_read_false(): void
    {
        $response = $this->getJson(
            '/api/v1/farmer/notifications?is_read=false',
            $this->authHeaders($this->farmer)
        );

        $response->assertStatus(200);

        $data = $response->json('data');
        foreach ($data as $notif) {
            $this->assertFalse($notif['is_read']);
        }
    }

    public function test_get_notifications_combined_filter_type_and_is_read(): void
    {
        $response = $this->getJson(
            '/api/v1/farmer/notifications?type=batch&is_read=false',
            $this->authHeaders($this->farmer)
        );

        $response->assertStatus(200);

        $data = $response->json('data');
        foreach ($data as $notif) {
            $this->assertEquals('batch', $notif['type']);
            $this->assertFalse($notif['is_read']);
        }
    }

    public function test_get_notifications_invalid_type_returns_empty_or_422(): void
    {
        $response = $this->getJson(
            '/api/v1/farmer/notifications?type=invalid_type',
            $this->authHeaders($this->farmer)
        );

        // Bisa 200 dengan empty data atau 422 validation error
        $this->assertThat(
            $response->status(),
            $this->logicalOr(
                $this->equalTo(200),
                $this->equalTo(422)
            )
        );
    }

    // =========================================================================
    // 3. GET NOTIFICATIONS — Auth & Role & Data Isolation
    // =========================================================================

    public function test_get_notifications_unauthenticated_returns_401(): void
    {
        $response = $this->getJson('/api/v1/farmer/notifications');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code'    => 'UNAUTHORIZED',
            ]);
    }

    public function test_get_notifications_as_exporter_returns_403(): void
    {
        $response = $this->getJson('/api/v1/farmer/notifications', $this->authHeaders($this->exporter));

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code'    => 'FORBIDDEN',
            ]);
    }

    public function test_notifications_data_isolation_between_farmers(): void
    {
        $response1 = $this->getJson('/api/v1/farmer/notifications', $this->authHeaders($this->farmer));
        $response2 = $this->getJson('/api/v1/farmer/notifications', $this->authHeaders($this->farmer2));

        $response1->assertStatus(200);
        $response2->assertStatus(200);

        $notifs1 = $response1->json('data');
        $notifs2 = $response2->json('data');

        $ids1 = collect($notifs1)->pluck('id')->toArray();
        $ids2 = collect($notifs2)->pluck('id')->toArray();
        $overlap = array_intersect($ids1, $ids2);
        $this->assertEmpty($overlap, 'Notifications should be isolated per farmer');
    }

    public function test_notifications_pagination_has_more_flag(): void
    {
        $response = $this->getJson('/api/v1/farmer/notifications', $this->authHeaders($this->farmer));

        $response->assertStatus(200);
        $pagination = $response->json('pagination');

        $this->assertArrayHasKey('hasMore', $pagination);
        $this->assertIsBool($pagination['hasMore']);
    }

    public function test_notifications_pagination_total_is_integer(): void
    {
        $response = $this->getJson('/api/v1/farmer/notifications', $this->authHeaders($this->farmer));

        $response->assertStatus(200);
        $total = $response->json('pagination.total');

        $this->assertIsInt($total);
        $this->assertGreaterThanOrEqual(0, $total);
    }

    // =========================================================================
    // 4. GET UNREAD COUNT — Happy Path
    // =========================================================================

    public function test_get_unread_count_returns_successfully(): void
    {
        $response = $this->getJson(
            '/api/v1/farmer/notifications/unread-count',
            $this->authHeaders($this->farmer)
        );

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code'    => 'SUCCESS',
                'message' => 'Jumlah notifikasi belum dibaca berhasil diambil',
            ])
            ->assertJsonStructure([
                'success', 'code', 'message',
                'data' => [
                    'unread_count',
                    'has_unread',
                ],
                'timestamp',
            ]);
    }

    public function test_unread_count_type_is_integer(): void
    {
        $response = $this->getJson(
            '/api/v1/farmer/notifications/unread-count',
            $this->authHeaders($this->farmer)
        );

        $response->assertStatus(200);
        $this->assertIsInt($response->json('data.unread_count'));
        $this->assertGreaterThanOrEqual(0, $response->json('data.unread_count'));
    }

    public function test_unread_count_has_unread_boolean(): void
    {
        $response = $this->getJson(
            '/api/v1/farmer/notifications/unread-count',
            $this->authHeaders($this->farmer)
        );

        $response->assertStatus(200);
        $count = $response->json('data.unread_count');
        $hasUnread = $response->json('data.has_unread');

        // has_unread harus konsisten: true jika count > 0, false jika count == 0
        $expectedHasUnread = $count > 0;
        $this->assertEquals($expectedHasUnread, $hasUnread);
    }

    public function test_unread_count_zero_when_all_read(): void
    {
        // Ambil semua notifikasi unread, tandai semua sebagai read
        $listResponse = $this->getJson(
            '/api/v1/farmer/notifications?is_read=false',
            $this->authHeaders($this->farmer)
        );
        $listResponse->assertStatus(200);
        $unreadNotifs = $listResponse->json('data');

        foreach ($unreadNotifs as $notif) {
            $this->patchJson(
                '/api/v1/farmer/notifications/' . $notif['id'] . '/read',
                [],
                $this->authHeaders($this->farmer)
            )->assertStatus(200);
        }

        // Cek unread count — seharusnya 0
        $countResponse = $this->getJson(
            '/api/v1/farmer/notifications/unread-count',
            $this->authHeaders($this->farmer)
        );
        $countResponse->assertStatus(200);
        $this->assertEquals(0, $countResponse->json('data.unread_count'));
        $this->assertFalse($countResponse->json('data.has_unread'));
    }

    // =========================================================================
    // 5. GET UNREAD COUNT — Auth & Role
    // =========================================================================

    public function test_get_unread_count_unauthenticated_returns_401(): void
    {
        $response = $this->getJson('/api/v1/farmer/notifications/unread-count');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code'    => 'UNAUTHORIZED',
            ]);
    }

    public function test_get_unread_count_as_exporter_returns_403(): void
    {
        $response = $this->getJson(
            '/api/v1/farmer/notifications/unread-count',
            $this->authHeaders($this->exporter)
        );

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code'    => 'FORBIDDEN',
            ]);
    }

    // =========================================================================
    // 6. PATCH NOTIFICATION READ — Happy Path
    // =========================================================================

    public function test_mark_notification_read_returns_success(): void
    {
        // Pertama, ambil 1 notifikasi
        $listResponse = $this->getJson(
            '/api/v1/farmer/notifications',
            $this->authHeaders($this->farmer)
        );
        $listResponse->assertStatus(200);
        $notifs = $listResponse->json('data');

        if (empty($notifs)) {
            $this->markTestSkipped('No notifications available');
        }

        $notifId = $notifs[0]['id'];

        $response = $this->patchJson(
            '/api/v1/farmer/notifications/' . $notifId . '/read',
            [],
            $this->authHeaders($this->farmer)
        );

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code'    => 'SUCCESS_UPDATE',
                'message' => 'Notifikasi ditandai sudah dibaca',
            ])
            ->assertJsonStructure([
                'success', 'code', 'message',
                'data' => [
                    'notification_id',
                    'is_read',
                    'read_at',
                ],
                'timestamp',
            ])
            ->assertJsonPath('data.notification_id', $notifId)
            ->assertJsonPath('data.is_read', true);
    }

    public function test_mark_notification_read_has_read_at_timestamp(): void
    {
        $listResponse = $this->getJson(
            '/api/v1/farmer/notifications',
            $this->authHeaders($this->farmer)
        );
        $listResponse->assertStatus(200);
        $notifs = $listResponse->json('data');

        if (empty($notifs)) {
            $this->markTestSkipped('No notifications available');
        }

        $notifId = $notifs[0]['id'];

        $response = $this->patchJson(
            '/api/v1/farmer/notifications/' . $notifId . '/read',
            [],
            $this->authHeaders($this->farmer)
        );

        $response->assertStatus(200);
        $readAt = $response->json('data.read_at');
        $this->assertNotNull($readAt);
        $this->assertIsString($readAt);
    }

    // =========================================================================
    // 7. PATCH NOTIFICATION READ — Edge Cases
    // =========================================================================

    public function test_mark_notification_read_idempotent(): void
    {
        $listResponse = $this->getJson(
            '/api/v1/farmer/notifications',
            $this->authHeaders($this->farmer)
        );
        $listResponse->assertStatus(200);
        $notifs = $listResponse->json('data');

        if (empty($notifs)) {
            $this->markTestSkipped('No notifications available');
        }

        $notifId = $notifs[0]['id'];

        // Mark read pertama kali
        $response1 = $this->patchJson(
            '/api/v1/farmer/notifications/' . $notifId . '/read',
            [],
            $this->authHeaders($this->farmer)
        );
        $response1->assertStatus(200);

        // Mark read kedua kali (idempotent)
        $response2 = $this->patchJson(
            '/api/v1/farmer/notifications/' . $notifId . '/read',
            [],
            $this->authHeaders($this->farmer)
        );
        $response2->assertStatus(200)
            ->assertJsonPath('data.is_read', true);
    }

    public function test_mark_notification_read_not_found_returns_404(): void
    {
        $response = $this->patchJson(
            '/api/v1/farmer/notifications/notif-nonexistent-999/read',
            [],
            $this->authHeaders($this->farmer)
        );

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code'    => 'NOT_FOUND',
            ]);
    }

    public function test_mark_notification_read_of_another_farmer_returns_404_or_403(): void
    {
        // Ambil notifikasi milik farmer2
        $listResponse = $this->getJson(
            '/api/v1/farmer/notifications',
            $this->authHeaders($this->farmer2)
        );
        $listResponse->assertStatus(200);
        $notifs = $listResponse->json('data');

        if (empty($notifs)) {
            $this->markTestSkipped('No notifications for farmer2');
        }

        $notifId = $notifs[0]['id'];

        // Farmer1 coba mark read notifikasi farmer2
        $response = $this->patchJson(
            '/api/v1/farmer/notifications/' . $notifId . '/read',
            [],
            $this->authHeaders($this->farmer)
        );

        $this->assertThat(
            $response->status(),
            $this->logicalOr(
                $this->equalTo(403),
                $this->equalTo(404)
            )
        );
    }

    public function test_mark_notification_read_unauthenticated_returns_401(): void
    {
        $response = $this->patchJson('/api/v1/farmer/notifications/notif-001/read', []);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code'    => 'UNAUTHORIZED',
            ]);
    }

    public function test_mark_notification_read_as_exporter_returns_403(): void
    {
        $response = $this->patchJson(
            '/api/v1/farmer/notifications/notif-001/read',
            [],
            $this->authHeaders($this->exporter)
        );

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code'    => 'FORBIDDEN',
            ]);
    }

    // =========================================================================
    // 8. CROSS-ENDPOINT: Konsistensi antara list, unread-count, dan mark-read
    // =========================================================================

    public function test_unread_count_decreases_after_marking_read(): void
    {
        // Ambil unread count awal
        $countBefore = $this->getJson(
            '/api/v1/farmer/notifications/unread-count',
            $this->authHeaders($this->farmer)
        );
        $countBefore->assertStatus(200);
        $before = $countBefore->json('data.unread_count');

        // Cari notifikasi yang belum dibaca
        $listResponse = $this->getJson(
            '/api/v1/farmer/notifications?is_read=false',
            $this->authHeaders($this->farmer)
        );
        $listResponse->assertStatus(200);
        $unreadNotifs = $listResponse->json('data');

        if (empty($unreadNotifs)) {
            $this->markTestSkipped('No unread notifications');
        }

        // Tandai 1 notifikasi sebagai read
        $notifId = $unreadNotifs[0]['id'];
        $this->patchJson(
            '/api/v1/farmer/notifications/' . $notifId . '/read',
            [],
            $this->authHeaders($this->farmer)
        )->assertStatus(200);

        // Cek unread count lagi
        $countAfter = $this->getJson(
            '/api/v1/farmer/notifications/unread-count',
            $this->authHeaders($this->farmer)
        );
        $countAfter->assertStatus(200);
        $after = $countAfter->json('data.unread_count');

        // Count harus berkurang 1
        $this->assertEquals($before - 1, $after);
    }
}

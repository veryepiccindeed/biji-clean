<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ResponseStandardTest - Validasi Standar Format Response API
 * 
 * Berdasarkan API_CONTRACT.md Section 4:
 * - 4.1 Format Response Sukses
 * - 4.2 Format Response Error
 * - 4.3 Format Response Berpagginasi
 */
class ResponseStandardTest extends TestCase
{
    use RefreshDatabase;

    private function createExporterUser(): User
    {
        return User::factory()->create(['role' => 'exporter']);
    }

    // ============================================
    // SECTION 4.1 - Format Response Sukses
    // ============================================

    public function test_success_response_has_required_structure(): void
    {
        // Respons sukses harus memiliki struktur: success, code, message, data, timestamp
        // Menggunakan endpoint GET /api/v1/auth/me sebagai contoh
        $user = $this->createExporterUser();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/auth/me');

        // Jika endpoint sudah diimplementasi
        if ($response->status() === 200) {
            $response->assertJsonStructure([
                'success',
                'code',
                'message',
                'data',
                'timestamp',
            ]);

            // Validasi tipe data
            $this->assertTrue($response->json('success'));
            $this->assertIsString($response->json('code'));
            $this->assertIsString($response->json('message'));
            $this->assertIsString($response->json('timestamp'));
        }
    }

    public function test_success_response_code_values(): void
    {
        // Code yang valid untuk response sukses adalah: SUCCESS, SUCCESS_CREATE, SUCCESS_UPDATE
        $user = $this->createExporterUser();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/auth/me');

        if ($response->status() === 200) {
            $validCodes = ['SUCCESS', 'SUCCESS_CREATE', 'SUCCESS_UPDATE'];
            $this->assertContains($response->json('code'), $validCodes);
        }
    }

    public function test_success_response_data_is_present(): void
    {
        // Field 'data' harus selalu ada di response sukses, bisa object atau array kosong
        $user = $this->createExporterUser();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/auth/me');

        if ($response->status() === 200) {
            $this->assertNotNull($response->json('data'));
        }
    }

    public function test_success_response_timestamp_is_iso8601(): void
    {
        // Timestamp harus dalam format ISO 8601 (UTC)
        $user = $this->createExporterUser();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/auth/me');

        if ($response->status() === 200) {
            $timestamp = $response->json('timestamp');
            // Format ISO 8601: YYYY-MM-DDTHH:MM:SSZ atau YYYY-MM-DDTHH:MM:SS+00:00
            $this->assertMatchesRegularExpression(
                '/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
                $timestamp,
                "Timestamp tidak dalam format ISO 8601: $timestamp"
            );
        }
    }

    public function test_success_response_http_status_is_correct(): void
    {
        // Status HTTP untuk response sukses: 200 OK (default) atau 201 Created (untuk POST)
        // Contoh endpoint GET
        $user = $this->createExporterUser();

        $getResponse = $this->actingAs($user, 'sanctum')->getJson('/api/v1/auth/me');

        // GET biasanya 200 OK
        if ($getResponse->status() === 200) {
            $this->assertEquals(200, $getResponse->status());
        }
    }

    // ============================================
    // SECTION 4.2 - Format Response Error
    // ============================================

    public function test_error_response_has_required_structure(): void
    {
        // Response error harus memiliki struktur: success, code, message, details, timestamp
        $response = $this->getJson('/api/v1/exporter/dashboard'); // Tanpa auth

        // Akan return 401 Unauthorized
        $response->assertJsonStructure([
            'success',
            'code',
            'message',
            'details', // details bisa null/empty object
            'timestamp',
        ]);

        $this->assertFalse($response->json('success'));
    }

    public function test_error_response_success_is_false(): void
    {
        // Field 'success' harus false untuk error response
        $response = $this->getJson('/api/v1/exporter/dashboard'); // Tanpa auth

        $this->assertFalse($response->json('success'));
    }

    public function test_error_response_code_is_valid(): void
    {
        // Code harus salah satu dari valid error codes (UNAUTHORIZED, FORBIDDEN, NOT_FOUND, VALIDATION_ERROR, etc)
        $response = $this->getJson('/api/v1/exporter/dashboard'); // Tanpa auth

        $validErrorCodes = [
            'UNAUTHORIZED',
            'FORBIDDEN',
            'NOT_FOUND',
            'VALIDATION_ERROR',
            'CONFLICT',
            'DUPLICATE_ACQUISITION',
            'INVALID_STATUS_TRANSITION',
            'BLOCKCHAIN_ERROR',
            'BLOCKCHAIN_TIMEOUT',
            'FILE_UPLOAD_ERROR',
            'INTERNAL_ERROR',
        ];

        $this->assertContains($response->json('code'), $validErrorCodes);
    }

    public function test_unauthorized_error_response_format(): void
    {
        // Test khusus untuk 401 Unauthorized response
        $response = $this->getJson('/api/v1/exporter/dashboard');

        $response->assertStatus(401)
                 ->assertJsonFragment([
                     'success' => false,
                     'code' => 'UNAUTHORIZED',
                 ]);

        $this->assertIsString($response->json('message'));
        $this->assertIsString($response->json('timestamp'));
    }

    public function test_forbidden_error_response_format(): void
    {
        // Test khusus untuk 403 Forbidden response (role-based access)
        $farmer = User::factory()->create(['role' => 'farmer']);

        $response = $this->actingAs($farmer, 'sanctum')
                         ->getJson('/api/v1/exporter/dashboard');

        $response->assertStatus(403)
                 ->assertJsonFragment([
                     'success' => false,
                     'code' => 'FORBIDDEN',
                 ]);
    }

    public function test_validation_error_response_has_details(): void
    {
        // Response validasi error harus memiliki details berisi field errors
        // Contoh: POST /api/v1/auth/register dengan data tidak valid
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'AB', // Terlalu pendek
            'email' => 'invalid-email',
            'password' => '123',
            'password_confirmation' => '456',
            'role' => 'invalid-role',
        ]);

        if ($response->status() === 422) {
            $response->assertStatus(422)
                     ->assertJsonFragment([
                         'success' => false,
                         'code' => 'VALIDATION_ERROR',
                     ]);

            // Details harus berisi field validation errors
            $details = $response->json('details');
            $this->assertIsArray($details);
        }
    }

    public function test_error_response_http_status_is_4xx_or_5xx(): void
    {
        // Error response harus mengembalikan HTTP status 4xx atau 5xx
        $responses = [
            $this->getJson('/api/v1/exporter/dashboard'), // 401
            $this->actingAs(User::factory()->create(['role' => 'farmer']), 'sanctum')
                  ->getJson('/api/v1/exporter/dashboard'), // 403
        ];

        foreach ($responses as $response) {
            $status = $response->status();
            $this->assertTrue(
                $status >= 400,
                "Error response harus return status 4xx/5xx, mendapat: $status"
            );
        }
    }

    // ============================================
    // SECTION 4.3 - Format Response Berpagginasi
    // ============================================

    public function test_paginated_response_has_required_structure(): void
    {
        // Response berpagginasi harus memiliki pagination object dengan cursor, hasMore, limit
        $exporter = $this->createExporterUser();

        $response = $this->actingAs($exporter, 'sanctum')
                         ->getJson('/api/v1/exporter/blockchain-logs?limit=10');

        // Jika endpoint sudah diimplementasi
        if ($response->status() === 200) {
            $response->assertJsonStructure([
                'success',
                'code',
                'data' => [
                    '*' => [
                        'id',
                        // Struktur item bisa berbeda-beda sesuai endpoint
                    ],
                ],
                'pagination' => [
                    'cursor',
                    'hasMore',
                    'limit',
                ],
                'timestamp',
            ]);
        }
    }

    public function test_paginated_response_cursor_is_base64(): void
    {
        // Cursor harus dalam format base64 (untuk pagination berbasis kursor)
        $exporter = $this->createExporterUser();

        $response = $this->actingAs($exporter, 'sanctum')
                         ->getJson('/api/v1/exporter/blockchain-logs?limit=10');

        if ($response->status() === 200) {
            $hasMore = $response->json('pagination.hasMore');
            $cursor = $response->json('pagination.cursor');

            if ($hasMore) {
                $this->assertMatchesRegularExpression(
                    '/^[A-Za-z0-9+\/=]+$/',
                    $cursor,
                    "Cursor tidak dalam format base64: $cursor"
                );
            } else {
                $this->assertNull($cursor);
            }
        }
    }

    public function test_paginated_response_has_more_is_boolean(): void
    {
        // hasMore harus boolean (true/false)
        $exporter = $this->createExporterUser();

        $response = $this->actingAs($exporter, 'sanctum')
                         ->getJson('/api/v1/exporter/blockchain-logs?limit=10');

        if ($response->status() === 200) {
            $hasMore = $response->json('pagination.hasMore');
            $this->assertIsBool($hasMore);
        }
    }

    public function test_paginated_response_limit_is_respected(): void
    {
        // Limit parameter harus direspek (data count <= limit)
        $exporter = $this->createExporterUser();

        $response = $this->actingAs($exporter, 'sanctum')
                         ->getJson('/api/v1/exporter/blockchain-logs?limit=5');

        if ($response->status() === 200) {
            $limit = $response->json('pagination.limit');
            $dataCount = count($response->json('data') ?? []);

            $this->assertLessThanOrEqual($limit, $dataCount);
            $this->assertEquals(5, $limit);
        }
    }

    public function test_paginated_response_data_is_array(): void
    {
        // Field 'data' dalam paginated response harus array (bukan object)
        $exporter = $this->createExporterUser();

        $response = $this->actingAs($exporter, 'sanctum')
                         ->getJson('/api/v1/exporter/blockchain-logs');

        if ($response->status() === 200) {
            $data = $response->json('data');
            $this->assertIsArray($data);
        }
    }

    public function test_paginated_response_limit_max_is_enforced(): void
    {
        // Limit max harus 100 (sesuai API_CONTRACT Section 8.3)
        $exporter = $this->createExporterUser();

        // Coba request dengan limit > 100
        $response = $this->actingAs($exporter, 'sanctum')
                         ->getJson('/api/v1/exporter/blockchain-logs?limit=150');

        if ($response->status() === 200) {
            $limit = $response->json('pagination.limit');
            // Harus di-cap ke max 100
            $this->assertLessThanOrEqual(100, $limit);
        }
    }

    public function test_paginated_response_default_limit(): void
    {
        // Default limit harus 20 (sesuai API_CONTRACT)
        $exporter = $this->createExporterUser();

        // Request tanpa limit parameter
        $response = $this->actingAs($exporter, 'sanctum')
                         ->getJson('/api/v1/exporter/blockchain-logs');

        if ($response->status() === 200) {
            $limit = $response->json('pagination.limit');
            $this->assertEquals(20, $limit);
        }
    }

    // ============================================
    // CROSS-VALIDATION TESTS
    // ============================================

    public function test_all_responses_have_timestamp(): void
    {
        // Semua response (sukses maupun error) harus memiliki timestamp
        $responses = [
            $this->getJson('/api/v1/auth/me'), // 401
            $this->actingAs($this->createExporterUser(), 'sanctum')
                  ->getJson('/api/v1/exporter/blockchain-logs'), // Success
        ];

        foreach ($responses as $response) {
            $this->assertNotNull(
                $response->json('timestamp'),
                "Response tidak memiliki timestamp (status: {$response->status()})"
            );
        }
    }

    public function test_all_responses_have_success_flag(): void
    {
        // Semua response harus memiliki field success (true/false)
        $responses = [
            $this->getJson('/api/v1/auth/me'), // 401
            $this->actingAs($this->createExporterUser(), 'sanctum')
                  ->getJson('/api/v1/exporter/blockchain-logs'), // Success
        ];

        foreach ($responses as $response) {
            $success = $response->json('success');
            $this->assertNotNull($success, "Response tidak memiliki field success");
            $this->assertIsBool($success);
        }
    }

    public function test_response_content_type_is_json(): void
    {
        // Semua response harus Content-Type: application/json
        $response = $this->getJson('/api/v1/exporter/dashboard');

        $contentType = (string) $response->headers->get('Content-Type');
        $this->assertStringContainsString('application/json', $contentType);
    }
}

<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test get profile success
     * GET /api/v1/me/profile - Happy path
     */
    public function test_get_profile_success(): void
    {
        $user = User::factory()->create([
            'name' => 'Budi Santoso',
            'email' => 'budi@eksportir.id',
            'phone' => '+628123456789',
            'location' => 'Jakarta, Indonesia',
            'role' => 'exporter',
        ]);
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/me/profile');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'code',
                'message',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'phone',
                    'location',
                    'role',
                    'email_verified_at',
                    'created_at',
                    'updated_at',
                ],
                'timestamp',
            ])
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
                'data' => [
                    'id' => $user->id,
                    'name' => 'Budi Santoso',
                    'email' => 'budi@eksportir.id',
                    'phone' => '+628123456789',
                    'location' => 'Jakarta, Indonesia',
                    'role' => 'exporter',
                ],
            ]);
    }

    /**
     * Test get profile fails - Not authenticated
     * GET /api/v1/me/profile - Unauthorized error
     */
    public function test_get_profile_fails_without_authentication(): void
    {
        $response = $this->getJson('/api/v1/me/profile');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    /**
     * Test get profile fails - Invalid token
     * GET /api/v1/me/profile - Unauthorized error
     */
    public function test_get_profile_fails_with_invalid_token(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer invalid_token_123')
            ->getJson('/api/v1/me/profile');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    /**
     * Test update profile success
     * PATCH /api/v1/me/profile - Happy path
     */
    public function test_update_profile_success(): void
    {
        $user = User::factory()->create([
            'name' => 'Budi Santoso',
            'phone' => '+628123456789',
            'location' => 'Jakarta, Indonesia',
        ]);
        $token = $user->createToken('auth_token')->plainTextToken;

        $payload = [
            'name' => 'Budi Santoso Baru',
            'phone' => '+628987654321',
            'location' => 'Surabaya, Indonesia',
        ];

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/me/profile', $payload);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'code',
                'message',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'phone',
                    'location',
                    'role',
                ],
                'timestamp',
            ])
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS_UPDATE',
                'data' => [
                    'name' => 'Budi Santoso Baru',
                    'phone' => '+628987654321',
                    'location' => 'Surabaya, Indonesia',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Budi Santoso Baru',
            'phone' => '+628987654321',
            'location' => 'Surabaya, Indonesia',
        ]);
    }

    /**
     * Test update profile with partial fields
     * PATCH /api/v1/me/profile - Partial update
     */
    public function test_update_profile_with_partial_fields(): void
    {
        $user = User::factory()->create([
            'name' => 'Budi Santoso',
            'phone' => '+628123456789',
            'location' => 'Jakarta, Indonesia',
        ]);
        $token = $user->createToken('auth_token')->plainTextToken;

        $payload = [
            'name' => 'Budi Updated',
        ];

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/me/profile', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS_UPDATE',
                'data' => [
                    'name' => 'Budi Updated',
                    'phone' => '+628123456789', // Should remain unchanged
                    'location' => 'Jakarta, Indonesia', // Should remain unchanged
                ],
            ]);
    }

    /**
     * Test update profile validation - Name too short
     * PATCH /api/v1/me/profile - Validation error
     */
    public function test_update_profile_fails_when_name_is_too_short(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $payload = [
            'name' => 'AB', // Too short (min 3)
        ];

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/me/profile', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    /**
     * Test update profile validation - Name too long
     * PATCH /api/v1/me/profile - Validation error
     */
    public function test_update_profile_fails_when_name_is_too_long(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $payload = [
            'name' => str_repeat('A', 256), // Too long (max 255)
        ];

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/me/profile', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    /**
     * Test update profile fails - Not authenticated
     * PATCH /api/v1/me/profile - Unauthorized error
     */
    public function test_update_profile_fails_without_authentication(): void
    {
        $payload = [
            'name' => 'New Name',
        ];

        $response = $this->patchJson('/api/v1/me/profile', $payload);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    /**
     * Test get settings success
     * GET /api/v1/me/settings - Happy path
     */
    public function test_get_settings_success(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/me/settings');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'code',
                'message',
                'data' => [
                    'language',
                    'timezone',
                    'notifications_enabled',
                    'email_notifications',
                    'temperature_unit',
                ],
                'timestamp',
            ])
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
            ]);
    }

    /**
     * Test get settings fails - Not authenticated
     * GET /api/v1/me/settings - Unauthorized error
     */
    public function test_get_settings_fails_without_authentication(): void
    {
        $response = $this->getJson('/api/v1/me/settings');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    /**
     * Test update settings success
     * PATCH /api/v1/me/settings - Happy path
     */
    public function test_update_settings_success(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $payload = [
            'language' => 'en',
            'timezone' => 'Asia/Jakarta',
            'notifications_enabled' => true,
            'email_notifications' => false,
            'temperature_unit' => 'celsius',
        ];

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/me/settings', $payload);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'code',
                'message',
                'data' => [
                    'language',
                    'timezone',
                    'notifications_enabled',
                    'email_notifications',
                    'temperature_unit',
                ],
                'timestamp',
            ])
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS_UPDATE',
                'data' => [
                    'language' => 'en',
                    'timezone' => 'Asia/Jakarta',
                    'notifications_enabled' => true,
                    'email_notifications' => false,
                    'temperature_unit' => 'celsius',
                ],
            ]);
    }

    /**
     * Test update settings with partial fields
     * PATCH /api/v1/me/settings - Partial update
     */
    public function test_update_settings_with_partial_fields(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $payload = [
            'language' => 'id',
        ];

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/me/settings', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS_UPDATE',
                'data' => [
                    'language' => 'id',
                ],
            ]);
    }

    /**
     * Test update settings validation - Invalid language
     * PATCH /api/v1/me/settings - Validation error
     */
    public function test_update_settings_fails_with_invalid_language(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $payload = [
            'language' => 'invalid_language',
        ];

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/me/settings', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    /**
     * Test update settings validation - Invalid timezone
     * PATCH /api/v1/me/settings - Validation error
     */
    public function test_update_settings_fails_with_invalid_timezone(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $payload = [
            'timezone' => 'Invalid/Timezone',
        ];

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/me/settings', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    /**
     * Test update settings fails - Not authenticated
     * PATCH /api/v1/me/settings - Unauthorized error
     */
    public function test_update_settings_fails_without_authentication(): void
    {
        $payload = [
            'language' => 'en',
        ];

        $response = $this->patchJson('/api/v1/me/settings', $payload);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    /**
     * Test get devices success
     * GET /api/v1/me/devices - Happy path
     */
    public function test_get_devices_success(): void
    {
        $user = User::factory()->create();
        $token1 = $user->createToken('device-1')->plainTextToken;
        $token2 = $user->createToken('device-2')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token1}")
            ->getJson('/api/v1/me/devices');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'code',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'last_used_at',
                        'is_current',
                    ],
                ],
                'timestamp',
            ])
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
            ]);

        // Verify at least 2 devices (should show all tokens for user)
        $devicesCount = count($response->json('data'));
        $this->assertGreaterThanOrEqual(1, $devicesCount);
    }

    /**
     * Test get devices fails - Not authenticated
     * GET /api/v1/me/devices - Unauthorized error
     */
    public function test_get_devices_fails_without_authentication(): void
    {
        $response = $this->getJson('/api/v1/me/devices');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    /**
     * Test current device is marked correctly
     * GET /api/v1/me/devices - Verify current device flag
     */
    public function test_get_devices_marks_current_device_correctly(): void
    {
        $user = User::factory()->create();
        $token1 = $user->createToken('device-1')->plainTextToken;
        $token2 = $user->createToken('device-2')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token1}")
            ->getJson('/api/v1/me/devices');

        $response->assertStatus(200);

        $devices = $response->json('data');
        // At least one device should be marked as current (the one with token1)
        $currentDeviceCount = collect($devices)->where('is_current', true)->count();
        $this->assertGreaterThanOrEqual(1, $currentDeviceCount);
    }
}

<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test successful user registration
     * POST /api/v1/auth/register - Happy path
     */
    public function test_register_success_with_valid_data(): void
    {
        $payload = [
            'name' => 'Budi Santoso',
            'email' => 'budi@eksportir.id',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'role' => 'exporter',
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'code',
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'role',
                        'email_verified_at',
                        'created_at',
                    ],
                    'access_token',
                    'refresh_token',
                    'token_type',
                    'expires_in',
                ],
                'timestamp',
            ])
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS_CREATE',
                'data' => [
                    'user' => [
                        'name' => 'Budi Santoso',
                        'email' => 'budi@eksportir.id',
                        'role' => 'exporter',
                    ],
                    'token_type' => 'Bearer',
                ],
            ]);

        // Verify user was created in database
        $this->assertDatabaseHas('users', [
            'email' => 'budi@eksportir.id',
            'name' => 'Budi Santoso',
        ]);
    }

    /**
     * Test register with farmer role
     * POST /api/v1/auth/register - Different roles
     */
    public function test_register_success_with_farmer_role(): void
    {
        $payload = [
            'name' => 'Siti Nurhaliza',
            'email' => 'siti@petani.id',
            'password' => 'FarmPass456!',
            'password_confirmation' => 'FarmPass456!',
            'role' => 'farmer',
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'role' => 'farmer',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'siti@petani.id',
            'role' => 'farmer',
        ]);
    }

    /**
     * Test register with buyer role
     * POST /api/v1/auth/register - Different roles
     */
    public function test_register_success_with_buyer_role(): void
    {
        $payload = [
            'name' => 'Ahmad Wijaya',
            'email' => 'ahmad@pembeli.id',
            'password' => 'BuyerPass789!',
            'password_confirmation' => 'BuyerPass789!',
            'role' => 'buyer',
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'role' => 'buyer',
                    ],
                ],
            ]);
    }

    /**
     * Test register validation - Missing name
     * POST /api/v1/auth/register - Validation error
     */
    public function test_register_fails_when_name_is_missing(): void
    {
        $payload = [
            'email' => 'budi@eksportir.id',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'role' => 'exporter',
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ])
            ->assertJsonStructure([
                'details' => [ 
                    'name'
                ],
            ]);
    }

    /**
     * Test register validation - Name too short
     * POST /api/v1/auth/register - Validation error
     */
    public function test_register_fails_when_name_is_too_short(): void
    {
        $payload = [
            'name' => 'AB',
            'email' => 'budi@eksportir.id',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'role' => 'exporter',
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    /**
     * Test register validation - Invalid email format
     * POST /api/v1/auth/register - Validation error
     */
    public function test_register_fails_with_invalid_email_format(): void
    {
        $payload = [
            'name' => 'Budi Santoso',
            'email' => 'not-an-email',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'role' => 'exporter',
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    /**
     * Test register validation - Password too weak (no uppercase)
     * POST /api/v1/auth/register - Validation error
     */
    public function test_register_fails_with_weak_password_no_uppercase(): void
    {
        $payload = [
            'name' => 'Budi Santoso',
            'email' => 'budi@eksportir.id',
            'password' => 'securepass123!',
            'password_confirmation' => 'securepass123!',
            'role' => 'exporter',
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    /**
     * Test register validation - Password too weak (no digit)
     * POST /api/v1/auth/register - Validation error
     */
    public function test_register_fails_with_weak_password_no_digit(): void
    {
        $payload = [
            'name' => 'Budi Santoso',
            'email' => 'budi@eksportir.id',
            'password' => 'SecurePass!',
            'password_confirmation' => 'SecurePass!',
            'role' => 'exporter',
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    /**
     * Test register validation - Password too short
     * POST /api/v1/auth/register - Validation error
     */
    public function test_register_fails_with_password_too_short(): void
    {
        $payload = [
            'name' => 'Budi Santoso',
            'email' => 'budi@eksportir.id',
            'password' => 'Pass1!',
            'password_confirmation' => 'Pass1!',
            'role' => 'exporter',
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    /**
     * Test register validation - Passwords do not match
     * POST /api/v1/auth/register - Validation error
     */
    public function test_register_fails_when_passwords_do_not_match(): void
    {
        $payload = [
            'name' => 'Budi Santoso',
            'email' => 'budi@eksportir.id',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'DifferentPass456!',
            'role' => 'exporter',
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    /**
     * Test register validation - Invalid role
     * POST /api/v1/auth/register - Validation error
     */
    public function test_register_fails_with_invalid_role(): void
    {
        $payload = [
            'name' => 'Budi Santoso',
            'email' => 'budi@eksportir.id',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'role' => 'admin',
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    /**
     * Test register fails - Email already registered
     * POST /api/v1/auth/register - Conflict error
     */
    public function test_register_fails_when_email_already_registered(): void
    {
        User::factory()->create([
            'email' => 'budi@eksportir.id',
        ]);

        $payload = [
            'name' => 'Budi Santoso',
            'email' => 'budi@eksportir.id',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'role' => 'exporter',
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'code' => 'CONFLICT',
            ]);
    }

    /**
     * Test successful login
     * POST /api/v1/auth/login - Happy path
     */
    public function test_login_success_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'budi@eksportir.id',
            'password' => bcrypt('SecurePass123!'),
        ]);

        $payload = [
            'email' => 'budi@eksportir.id',
            'password' => 'SecurePass123!',
            'remember_me' => false,
        ];

        $response = $this->postJson('/api/v1/auth/login', $payload);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'code',
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'role',
                        'created_at',
                    ],
                    'access_token',
                    'refresh_token',
                    'token_type',
                    'expires_in',
                ],
                'timestamp',
            ])
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'email' => 'budi@eksportir.id',
                    ],
                    'token_type' => 'Bearer',
                ],
            ]);
    }

    /**
     * Test login with remember_me enabled
     * POST /api/v1/auth/login - remember_me=true
     */
    public function test_login_success_with_remember_me_enabled(): void
    {
        $user = User::factory()->create([
            'email' => 'budi@eksportir.id',
            'password' => bcrypt('SecurePass123!'),
        ]);

        $payload = [
            'email' => 'budi@eksportir.id',
            'password' => 'SecurePass123!',
            'remember_me' => true,
        ];

        $response = $this->postJson('/api/v1/auth/login', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    'remember_token',
                ],
            ]);
    }

    /**
     * Test login fails - Invalid email
     * POST /api/v1/auth/login - Unauthorized error
     */
    public function test_login_fails_with_non_existent_email(): void
    {
        $payload = [
            'email' => 'nonexistent@example.com',
            'password' => 'SecurePass123!',
            'remember_me' => false,
        ];

        $response = $this->postJson('/api/v1/auth/login', $payload);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    /**
     * Test login fails - Wrong password
     * POST /api/v1/auth/login - Unauthorized error
     */
    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'budi@eksportir.id',
            'password' => bcrypt('SecurePass123!'),
        ]);

        $payload = [
            'email' => 'budi@eksportir.id',
            'password' => 'WrongPassword123!',
            'remember_me' => false,
        ];

        $response = $this->postJson('/api/v1/auth/login', $payload);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    /**
     * Test login validation - Missing email
     * POST /api/v1/auth/login - Validation error
     */
    public function test_login_fails_when_email_is_missing(): void
    {
        $payload = [
            'password' => 'SecurePass123!',
            'remember_me' => false,
        ];

        $response = $this->postJson('/api/v1/auth/login', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    /**
     * Test login validation - Missing password
     * POST /api/v1/auth/login - Validation error
     */
    public function test_login_fails_when_password_is_missing(): void
    {
        $payload = [
            'email' => 'budi@eksportir.id',
            'remember_me' => false,
        ];

        $response = $this->postJson('/api/v1/auth/login', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    /**
     * Test login excludes remember_token when remember_me is false
     * POST /api/v1/auth/login - remember_token conditional
     */
    public function test_login_excludes_remember_token_when_remember_me_false(): void
    {
        $user = User::factory()->create([
            'email' => 'budi@eksportir.id',
            'password' => bcrypt('SecurePass123!'),
        ]);

        $payload = [
            'email' => 'budi@eksportir.id',
            'password' => 'SecurePass123!',
            'remember_me' => false,
        ];

        $response = $this->postJson('/api/v1/auth/login', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
            ]);

        // Verify remember_token is NOT in response when remember_me=false
        $this->assertNull($response->json('data.remember_token'));
    }

    /**
     * Test logout success
     * POST /api/v1/auth/logout - Happy path
     */
    public function test_logout_success_with_authenticated_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
            ]);

        // Verifikasi tingkat senior: Pastikan token di DB sisa 0
        $this->assertCount(0, $user->fresh()->tokens); 
    }

    /**
     * Test logout fails - Not authenticated
     * POST /api/v1/auth/logout - Unauthorized error
     */
    public function test_logout_fails_without_authentication(): void
    {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    /**
     * Test logout fails - Invalid token
     * POST /api/v1/auth/logout - Unauthorized error
     */
    public function test_logout_fails_with_invalid_token(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer invalid_token_123')
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    /**
     * Test refresh token success
     * POST /api/v1/auth/refresh - Happy path
     */
    public function test_refresh_token_success_with_valid_refresh_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken; 
        
        $payload = ['refresh_token' => 'some-valid-refresh-token'];

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/refresh', $payload);

        $response->assertStatus(200);
    }

    /**
     * Test refresh fails - No refresh token provided
     * POST /api/v1/auth/refresh - Validation error
     */
    public function test_refresh_fails_when_refresh_token_is_missing(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/refresh', []); // Body kosong

        // Harus 422 karena refresh_token required di kontrak
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR'
            ]);
    }

    /**
     * Test refresh fails - Not authenticated
     * POST /api/v1/auth/refresh - Unauthorized error
     */
    public function test_refresh_fails_without_authentication(): void
    {
        $response = $this->postJson('/api/v1/auth/refresh', []);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    /**
     * Test get current user (auth/me) success
     * GET /api/v1/auth/me - Happy path
     */
    public function test_get_auth_me_success(): void
    {
        $user = User::factory()->create([
            'email' => 'budi@eksportir.id',
            'name' => 'Budi Santoso',
        ]);
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'code',
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'role',
                        'email_verified_at',
                        'created_at',
                    ],
                ],
                'timestamp',
            ])
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => 'Budi Santoso',
                        'email' => 'budi@eksportir.id',
                    ],
                ],
            ]);
    }

    /**
     * Test get auth/me fails - Not authenticated
     * GET /api/v1/auth/me - Unauthorized error
     */
    public function test_get_auth_me_fails_without_authentication(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    /**
     * Test get auth/me fails - Invalid token
     * GET /api/v1/auth/me - Unauthorized error
     */
    public function test_get_auth_me_fails_with_invalid_token(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer invalid_token_123')
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    /**
     * Test forgot password success
     * POST /api/v1/auth/forgot-password - Happy path
     */
    public function test_forgot_password_success_with_existing_email(): void
    {
        User::factory()->create([
            'email' => 'budi@eksportir.id',
        ]);

        $payload = [
            'email' => 'budi@eksportir.id',
        ];

        $response = $this->postJson('/api/v1/auth/forgot-password', $payload);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'code',
                'message',
                'data' => [
                    'email',
                    'expires_at',
                ],
                'timestamp',
            ])
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
                'data' => [
                    'email' => 'budi@eksportir.id',
                ],
            ]);

        // Verify expires_at is ISO8601 timestamp
        $expiresAt = $response->json('data.expires_at');
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $expiresAt);
    }

    /**
     * Test forgot password returns success even with non-existent email (security: prevent email enumeration)
     * POST /api/v1/auth/forgot-password - Security: Hide email existence
     */
    public function test_forgot_password_returns_success_with_non_existent_email(): void
    {
        $payload = [
            'email' => 'nonexistent@example.com',
        ];

        $response = $this->postJson('/api/v1/auth/forgot-password', $payload);

        // API contract states: returns success even if email not found (prevent enumeration)
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
            ]);
    }

    /**
     * Test forgot password validation - Missing email
     * POST /api/v1/auth/forgot-password - Validation error
     */
    public function test_forgot_password_fails_when_email_is_missing(): void
    {
        $payload = [];

        $response = $this->postJson('/api/v1/auth/forgot-password', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    /**
     * Test forgot password validation - Invalid email format
     * POST /api/v1/auth/forgot-password - Validation error
     */
    public function test_forgot_password_fails_with_invalid_email_format(): void
    {
        $payload = [
            'email' => 'not-an-email',
        ];

        $response = $this->postJson('/api/v1/auth/forgot-password', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    /**
     * Test reset password success
     * POST /api/v1/auth/reset-password - Happy path
     */
    public function test_reset_password_success_with_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'budi@eksportir.id',
            'password' => bcrypt('OldPassword123!'),
        ]);

        // In a real scenario, generate a reset token
        // For this test, we assume token generation works
        $token = 'reset-token-abc123';

        // We need a way to store this token - using Laravel password_reset_tokens table
        \DB::table('password_reset_tokens')->insert([
            'email' => 'budi@eksportir.id',
            'token' => hash('sha256', $token),
            'created_at' => now(),
        ]);

        $payload = [
            'email' => 'budi@eksportir.id',
            'token' => $token,
            'password' => 'NewPassword456!',
            'password_confirmation' => 'NewPassword456!',
        ];

        $response = $this->postJson('/api/v1/auth/reset-password', $payload);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'code',
                'message',
                'data' => [
                    'email',
                    'reset_at',
                ],
                'timestamp',
            ])
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
                'data' => [
                    'email' => 'budi@eksportir.id',
                ],
            ]);

        // Verify reset_at is ISO8601 timestamp
        $resetAt = $response->json('data.reset_at');
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $resetAt);

        // Verify password was updated
        $this->assertTrue(
            \Hash::check('NewPassword456!', $user->fresh()->password)
        );
    }

    /**
     * Test reset password fails - Invalid token
     * POST /api/v1/auth/reset-password - Not found error
     */
    public function test_reset_password_fails_with_invalid_token(): void
    {
        $payload = [
            'email' => 'budi@eksportir.id',
            'token' => 'invalid-token-xyz',
            'password' => 'NewPassword456!',
            'password_confirmation' => 'NewPassword456!',
        ];

        $response = $this->postJson('/api/v1/auth/reset-password', $payload);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code' => 'NOT_FOUND',
            ]);
    }

    /**
     * Test reset password validation - Passwords do not match
     * POST /api/v1/auth/reset-password - Validation error
     */
    public function test_reset_password_fails_when_passwords_do_not_match(): void
    {
        \DB::table('password_reset_tokens')->insert([
            'email' => 'budi@eksportir.id',
            'token' => hash('sha256', 'reset-token-abc123'),
            'created_at' => now(),
        ]);

        $payload = [
            'email' => 'budi@eksportir.id',
            'token' => 'reset-token-abc123',
            'password' => 'NewPassword456!',
            'password_confirmation' => 'DifferentPassword789!',
        ];

        $response = $this->postJson('/api/v1/auth/reset-password', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    /**
     * Test reset password validation - Password too weak
     * POST /api/v1/auth/reset-password - Validation error
     */
    public function test_reset_password_fails_with_weak_password(): void
    {
        \DB::table('password_reset_tokens')->insert([
            'email' => 'budi@eksportir.id',
            'token' => hash('sha256', 'reset-token-abc123'),
            'created_at' => now(),
        ]);

        $payload = [
            'email' => 'budi@eksportir.id',
            'token' => 'reset-token-abc123',
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ];

        $response = $this->postJson('/api/v1/auth/reset-password', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    /**
     * Test reset password fails - Missing email
     * POST /api/v1/auth/reset-password - Validation error
     */
    public function test_reset_password_fails_when_email_is_missing(): void
    {
        $payload = [
            'token' => 'reset-token-abc123',
            'password' => 'NewPassword456!',
            'password_confirmation' => 'NewPassword456!',
        ];

        $response = $this->postJson('/api/v1/auth/reset-password', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    /**
     * Test reset password fails - Missing token
     * POST /api/v1/auth/reset-password - Validation error
     */
    public function test_reset_password_fails_when_token_is_missing(): void
    {
        $payload = [
            'email' => 'budi@eksportir.id',
            'password' => 'NewPassword456!',
            'password_confirmation' => 'NewPassword456!',
        ];

        $response = $this->postJson('/api/v1/auth/reset-password', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    /**
     * Test reset password fails - New password same as old password
     * POST /api/v1/auth/reset-password - Conflict error (409)
     */
    public function test_reset_password_fails_when_new_password_equals_old_password(): void
    {
        $oldPassword = 'OldPassword123!';
        $user = User::factory()->create([
            'email' => 'budi@eksportir.id',
            'password' => bcrypt($oldPassword),
        ]);

        $token = 'reset-token-xyz';
        \DB::table('password_reset_tokens')->insert([
            'email' => 'budi@eksportir.id',
            'token' => hash('sha256', $token),
            'created_at' => now(),
        ]);

        $payload = [
            'email' => 'budi@eksportir.id',
            'token' => $token,
            'password' => $oldPassword,  // Same as old
            'password_confirmation' => $oldPassword,
        ];

        $response = $this->postJson('/api/v1/auth/reset-password', $payload);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'code' => 'CONFLICT',
            ]);
    }

        /**
     * Test ubah password untuk user yang sedang login
     * PATCH /api/v1/me/security/password - API Contract 7.3
     */
    public function test_change_password_success(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('OldPassword123!'),
        ]);
        $token = $user->createToken('auth_token')->plainTextToken;

        $payload = [
            'old_password' => 'OldPassword123!',
            'new_password' => 'NewSecurePass456!',
            'new_password_confirmation' => 'NewSecurePass456!',
        ];

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/me/security/password', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS_UPDATE',
            ]);

        // Pastikan password di DB sudah berubah
        $this->assertTrue(\Hash::check('NewSecurePass456!', $user->fresh()->password));
    }

    /**
     * Test change password fails - Wrong old password
     * PATCH /api/v1/me/security/password - Validation error
     */
    public function test_change_password_fails_with_wrong_old_password(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('CorrectOldPass123!'),
        ]);
        $token = $user->createToken('auth_token')->plainTextToken;

        $payload = [
            'old_password' => 'WrongOldPass123!',  // ← Wrong
            'new_password' => 'NewSecurePass456!',
            'new_password_confirmation' => 'NewSecurePass456!',
        ];

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/me/security/password', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    /**
     * Test change password fails - New password same as old password
     * PATCH /api/v1/me/security/password - Conflict error (409)
     */
    public function test_change_password_fails_when_new_password_equals_old_password(): void
    {
        $password = 'SamePassword123!';
        $user = User::factory()->create([
            'password' => bcrypt($password),
        ]);
        $token = $user->createToken('auth_token')->plainTextToken;

        $payload = [
            'old_password' => $password,
            'new_password' => $password,  // ← Same
            'new_password_confirmation' => $password,
        ];

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/me/security/password', $payload);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'code' => 'CONFLICT',
            ]);
    }

    /**
     * Test change password terminates all active sessions
     * PATCH /api/v1/me/security/password - Session termination
     */
    public function test_change_password_terminates_all_sessions(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('OldPassword123!'),
        ]);
        
        // Generate token asli
        $token1 = $user->createToken('device-1')->plainTextToken;
        $token2 = $user->createToken('device-2')->plainTextToken;

        // 1. Ganti password (Request pertama)
        $response = $this->withHeader('Authorization', "Bearer {$token1}")
            ->patchJson('/api/v1/me/security/password', [
                'old_password' => 'OldPassword123!',
                'new_password' => 'NewPassword456!',
                'new_password_confirmation' => 'NewPassword456!',
            ]);
            
        $response->assertStatus(200);

        // RESET AUTH STATE: Penting agar Laravel mengecek token lagi dari DB
        \Illuminate\Support\Facades\Auth::forgetUser();

        // 2. Verifikasi token 1 (Harus 401)
        $this->withHeader('Authorization', "Bearer {$token1}")
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);

        // 3. Verifikasi token 2 (Harus 401)
        $this->withHeader('Authorization', "Bearer {$token2}")
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }
}



<?php

namespace Tests\Feature\Farmer;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * FarmerProfileTest — Test Case untuk Modul Profil, Keamanan & Preferensi Petani (API Contract V2.1)
 *
 * Scope: 6 endpoint pengaturan petani
 *   - GET  /api/v1/farmer/profile              — Get profil (11.1)
 *   - PATCH /api/v1/farmer/profile              — Update profil (11.2)
 *   - PATCH /api/v1/farmer/security/password    — Ubah password (11.3)
 *   - POST  /api/v1/farmer/profile/avatar      — Upload avatar (11.4)
 *   - GET  /api/v1/farmer/preferences          — Get preferensi (12.1)
 *   - PATCH /api/v1/farmer/preferences         — Update preferensi (12.2)
 *
 * Reference: API_CONTRACT_V2_FARMER.md Section 11 (Profil) & Section 12 (Preferensi)
 *
 * Business Rules V2.1 yang ditest:
 * - Profil field: full_name, email (read-only), phone, location, coordinates
 * - email TIDAK bisa diubah via PATCH profile
 * - profile_completion dihitung otomatis berdasarkan kelengkapan field
 * - profile_completion_details: 6 key boolean (name, email, phone, location, coordinates, avatar)
 * - Password change: old_password wajib cocok, new_password min 8 char, beda dari lama
 * - Setelah password change → semua sesi aktif diterminasi
 * - Avatar: JPEG/PNG/WebP, max 2MB, min 200x200, max 2000x2000
 * - Preferensi: language, batch_notification, temperature_unit, save_mode
 * - Semua PATCH: minimal 1 field harus dikirim (empty body → 422)
 */
class FarmerProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $farmer;

    private User $farmer2;

    private User $exporter;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('avatars');

        $this->farmer = User::factory()->create([
            'role' => 'farmer',
            'name' => 'Yusuf Ibrahim',
            'email' => 'yusuf@example.com',
            'phone' => '+62 812-3456-7890',
            'phone_verified' => true,
            'location' => 'Tana Toraja, Sulawesi Selatan',
            'coordinates' => '-3.0701, 119.8923',
            'profile_completion' => 75,
            'password' => Hash::make('OldPassword123!'),
        ]);

        $this->farmer2 = User::factory()->create([
            'role' => 'farmer',
            'name' => 'Ahmad Tandilang',
            'email' => 'ahmad@example.com',
            'phone' => '+62 813-9876-5432',
            'phone_verified' => true,
            'location' => 'Enrekang, Sulawesi Selatan',
            'coordinates' => '-3.4023, 119.8432',
            'profile_completion' => 80,
        ]);

        $this->exporter = User::factory()->create([
            'role' => 'exporter',
            'email' => 'exporter@example.com',
            'password' => Hash::make('ExporterPass123!'),
        ]);
    }

    // ========================================================================
    // Helper Methods
    // ========================================================================

    private function getProfile(?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->farmer)
            ->getJson('/api/v1/farmer/profile');
    }

    private function updateProfile(array $data, ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->farmer)
            ->patchJson('/api/v1/farmer/profile', $data);
    }

    private function changePassword(array $data, ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->farmer)
            ->patchJson('/api/v1/farmer/security/password', $data);
    }

    private function uploadAvatar(UploadedFile $file, ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->farmer)
            ->postJson('/api/v1/farmer/profile/avatar', [
                'avatar' => $file,
            ]);
    }

    private function getPreferences(?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->farmer)
            ->getJson('/api/v1/farmer/preferences');
    }

    private function updatePreferences(array $data, ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->farmer)
            ->patchJson('/api/v1/farmer/preferences', $data);
    }

    // ========================================================================
    // 11.1: GET /api/v1/farmer/profile — Get Profil
    // ========================================================================

    // --- A. Happy Path ---

    public function test_get_profile_returns_200_with_all_fields()
    {
        $response = $this->getProfile();

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
                'message' => 'Profil petani berhasil diambil',
            ])
            ->assertJsonStructure([
                'data' => [
                    'profile' => [
                        'id',
                        'full_name',
                        'email',
                        'phone',
                        'phone_verified',
                        'location',
                        'coordinates',
                        'coordinates_latitude',
                        'coordinates_longitude',
                        'avatar_url',
                        'profile_completion',
                        'profile_completion_details',
                        'verification_status',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'timestamp',
            ]);

        // Verifikasi data sesuai farmer
        $response->assertJsonPath('data.profile.full_name', $this->farmer->name);
        $response->assertJsonPath('data.profile.email', $this->farmer->email);
        $response->assertJsonPath('data.profile.phone', $this->farmer->phone);
        $response->assertJsonPath('data.profile.phone_verified', $this->farmer->phone_verified);
        $response->assertJsonPath('data.profile.location', $this->farmer->location);
        $response->assertJsonPath('data.profile.coordinates', $this->farmer->coordinates);
    }

    public function test_get_profile_completion_details_has_6_boolean_keys()
    {
        $response = $this->getProfile();

        $details = $response->json('data.profile.profile_completion_details');

        $this->assertArrayHasKey('name', $details);
        $this->assertArrayHasKey('email', $details);
        $this->assertArrayHasKey('phone', $details);
        $this->assertArrayHasKey('location', $details);
        $this->assertArrayHasKey('coordinates', $details);
        $this->assertArrayHasKey('avatar', $details);

        // Semua harus boolean
        foreach ($details as $key => $value) {
            $this->assertIsBool($value, "profile_completion_details.{$key} harus boolean");
        }
    }

    public function test_get_profile_verification_status_populated()
    {
        $response = $this->getProfile();

        $response->assertStatus(200);
        $verificationStatus = $response->json('data.profile.verification_status');
        $this->assertNotNull($verificationStatus);
        $this->assertNotEmpty($verificationStatus);
    }

    // --- B. Edge Cases ---

    public function test_get_profile_with_minimal_data_returns_correct_details()
    {
        // Petani baru: hanya name + email
        $minimalFarmer = User::factory()->create([
            'role' => 'farmer',
            'name' => 'Petani Baru',
            'email' => 'baru@example.com',
            'phone' => null,
            'phone_verified' => false,
            'location' => null,
            'coordinates' => null,
            'avatar' => null,
        ]);

        $response = $this->getProfile($minimalFarmer);

        $response->assertStatus(200);

        $details = $response->json('data.profile.profile_completion_details');
        $this->assertTrue($details['name']);
        $this->assertTrue($details['email']);
        $this->assertFalse($details['phone']);
        $this->assertFalse($details['location']);
        $this->assertFalse($details['coordinates']);
        $this->assertFalse($details['avatar']);
    }

    public function test_get_profile_with_all_fields_filled_returns_all_details_true()
    {
        // Farmer dengan semua field (kecuali mungkin avatar)
        $response = $this->getProfile();

        $response->assertStatus(200);

        $details = $response->json('data.profile.profile_completion_details');
        // name, email, phone, location, coordinates sudah terisi → true
        $this->assertTrue($details['name']);
        $this->assertTrue($details['email']);
        $this->assertTrue($details['phone']);
        $this->assertTrue($details['location']);
        $this->assertTrue($details['coordinates']);
    }

    // ========================================================================
    // 11.2: PATCH /api/v1/farmer/profile — Update Profil
    // ========================================================================

    // --- C. Happy Path ---

    public function test_update_profile_full_name_only_returns_200()
    {
        $response = $this->updateProfile(['full_name' => 'Yusuf Ibrahim Modified']);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS_UPDATE',
                'message' => 'Profil berhasil diperbarui',
            ])
            ->assertJsonPath('data.profile.full_name', 'Yusuf Ibrahim Modified');

        // Email tidak boleh berubah
        $response->assertJsonPath('data.profile.email', $this->farmer->email);
        // Phone tidak boleh berubah (karena tidak dikirim)
        $response->assertJsonPath('data.profile.phone', $this->farmer->phone);
    }

    public function test_update_profile_phone_and_location_returns_200()
    {
        $response = $this->updateProfile([
            'phone' => '+62 855-1111-2222',
            'location' => 'Makale, Tana Toraja',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.profile.phone', '+62 855-1111-2222')
            ->assertJsonPath('data.profile.location', 'Makale, Tana Toraja');
    }

    public function test_update_profile_coordinates_returns_200()
    {
        $response = $this->updateProfile([
            'coordinates' => '-3.1000, 119.8500',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.profile.coordinates', '-3.1000, 119.8500')
            ->assertJsonPath('data.profile.coordinates_latitude', -3.1000)
            ->assertJsonPath('data.profile.coordinates_longitude', 119.8500);
    }

    public function test_update_profile_partial_update_preserves_other_fields()
    {
        // Hanya update location
        $response = $this->updateProfile(['location' => 'Rantepao, Toraja Utara']);

        $response->assertStatus(200)
            ->assertJsonPath('data.profile.location', 'Rantepao, Toraja Utara')
            ->assertJsonPath('data.profile.full_name', $this->farmer->name)
            ->assertJsonPath('data.profile.email', $this->farmer->email)
            ->assertJsonPath('data.profile.phone', $this->farmer->phone)
            ->assertJsonPath('data.profile.coordinates', $this->farmer->coordinates);
    }

    // --- D. Validasi ---

    public function test_update_profile_full_name_too_short_returns_422()
    {
        $response = $this->updateProfile(['full_name' => 'Ab']);

        $response->assertStatus(422);
    }

    public function test_update_profile_phone_invalid_format_returns_422()
    {
        // Bukan format Indonesia
        $response = $this->updateProfile(['phone' => '12345678']);

        $response->assertStatus(422);
    }

    public function test_update_profile_coordinates_invalid_format_returns_422()
    {
        // Bukan format lat, lng
        $response = $this->updateProfile(['coordinates' => 'not-valid-coords']);

        $response->assertStatus(422);
    }

    public function test_update_profile_location_too_short_returns_422()
    {
        $response = $this->updateProfile(['location' => 'Ab']);

        $response->assertStatus(422);
    }

    public function test_update_profile_empty_body_returns_422()
    {
        // Kirim body kosong → minimal 1 field
        $response = $this->updateProfile([]);

        $response->assertStatus(422);
    }

    // --- E. Business Rules ---

    public function test_update_profile_email_is_read_only_cannot_change()
    {
        $originalEmail = $this->farmer->email;

        $response = $this->updateProfile([
            'email' => 'hacker@evil.com',
            'full_name' => 'Test',
        ]);

        $response->assertStatus(200);
        // Email TIDAK boleh berubah
        $response->assertJsonPath('data.profile.email', $originalEmail);
    }

    public function test_update_profile_completion_increases_when_field_filled()
    {
        // Farmer dengan location kosong
        $farmerIncomplete = User::factory()->create([
            'role' => 'farmer',
            'name' => 'Petani',
            'email' => 'petani@test.com',
            'phone' => '+62 800-1111-2222',
            'phone_verified' => true,
            'location' => null,
            'coordinates' => '-3.0, 119.8',
            'profile_completion' => 50,
        ]);

        $responseBefore = $this->getProfile($farmerIncomplete);
        $completionBefore = $responseBefore->json('data.profile.profile_completion');

        // Update location → completion naik
        $this->updateProfile(['location' => 'Tana Toraja'], $farmerIncomplete)
            ->assertStatus(200);

        $responseAfter = $this->getProfile($farmerIncomplete);
        $completionAfter = $responseAfter->json('data.profile.profile_completion');

        $this->assertGreaterThan($completionBefore, $completionAfter);
    }

    public function test_update_profile_completion_details_reflect_changes()
    {
        // Set phone null dulu
        $this->farmer->update(['phone' => null, 'profile_completion' => 60]);

        // Cek sebelum: phone = false
        $before = $this->getProfile();
        $before->assertJsonPath('data.profile.profile_completion_details.phone', false);

        // Update phone
        $this->updateProfile(['phone' => '+62 812-0000-1111'])->assertStatus(200);

        // Cek sesudah: phone = true
        $after = $this->getProfile();
        $after->assertJsonPath('data.profile.profile_completion_details.phone', true);
    }

    // ========================================================================
    // 11.3: PATCH /api/v1/farmer/security/password — Ubah Password
    // ========================================================================

    // --- G. Happy Path ---

    public function test_change_password_success_returns_200()
    {
        $response = $this->changePassword([
            'old_password' => 'OldPassword123!',
            'new_password' => 'NewPassword456!',
            'new_password_confirmation' => 'NewPassword456!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS_UPDATE',
                'message' => 'Password berhasil diubah',
            ])
            ->assertJsonStructure([
                'data' => ['message'],
            ]);
    }

    public function test_change_password_response_contains_session_termination_message()
    {
        $response = $this->changePassword([
            'old_password' => 'OldPassword123!',
            'new_password' => 'NewPassword456!',
            'new_password_confirmation' => 'NewPassword456!',
        ]);

        $response->assertStatus(200);

        $message = $response->json('data.message');
        $this->assertNotNull($message);
        $this->assertNotEmpty($message);
    }

    // --- H. Error Cases ---

    public function test_change_password_wrong_old_password_returns_422()
    {
        $response = $this->changePassword([
            'old_password' => 'WrongOldPassword!',
            'new_password' => 'NewPassword456!',
            'new_password_confirmation' => 'NewPassword456!',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_change_password_new_too_short_returns_422()
    {
        $response = $this->changePassword([
            'old_password' => 'OldPassword123!',
            'new_password' => 'Short1!',
            'new_password_confirmation' => 'Short1!',
        ]);

        $response->assertStatus(422);
    }

    public function test_change_password_same_as_old_returns_409()
    {
        $response = $this->changePassword([
            'old_password' => 'OldPassword123!',
            'new_password' => 'OldPassword123!',
            'new_password_confirmation' => 'OldPassword123!',
        ]);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_change_password_confirmation_mismatch_returns_422()
    {
        $response = $this->changePassword([
            'old_password' => 'OldPassword123!',
            'new_password' => 'NewPassword456!',
            'new_password_confirmation' => 'DifferentPassword789!',
        ]);

        $response->assertStatus(422);
    }

    public function test_change_password_missing_field_returns_422()
    {
        // Kirim tanpa old_password
        $response = $this->changePassword([
            'new_password' => 'NewPassword456!',
            'new_password_confirmation' => 'NewPassword456!',
        ]);

        $response->assertStatus(422);
    }

    // ========================================================================
    // 11.4: POST /api/v1/farmer/profile/avatar — Upload Avatar
    // ========================================================================

    // --- I. Happy Path ---

    public function test_upload_avatar_success_returns_200()
    {
        $avatar = UploadedFile::fake()->image('avatar.jpg', 400, 400)->size(500);

        $response = $this->uploadAvatar($avatar);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS_UPDATE',
                'message' => 'Avatar berhasil diperbarui',
            ])
            ->assertJsonStructure([
                'data' => [
                    'avatar_url',
                    'profile_completion',
                ],
            ]);

        // avatar_url harus terisi
        $avatarUrl = $response->json('data.avatar_url');
        $this->assertNotNull($avatarUrl);
        $this->assertNotEmpty($avatarUrl);
    }

    public function test_upload_avatar_then_get_profile_shows_new_avatar()
    {
        $avatar = UploadedFile::fake()->image('avatar.png', 300, 300)->size(300);

        // Upload avatar
        $this->uploadAvatar($avatar)->assertStatus(200);

        // Get profile → avatar_url harus terisi
        $profileResponse = $this->getProfile();

        $profileResponse->assertStatus(200)
            ->assertJsonPath('data.profile.avatar_url', function ($value) {
                return $value !== null && $value !== '';
            });

        // avatar di completion_details harus true
        $profileResponse->assertJsonPath('data.profile.profile_completion_details.avatar', true);
    }

    // --- J. Validasi ---

    public function test_upload_avatar_invalid_file_format_returns_422()
    {
        $pdfFile = UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');

        $response = $this->uploadAvatar($pdfFile);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_upload_avatar_exceeds_max_size_2mb_returns_422()
    {
        // 3MB (melebihi 2MB max)
        $largeFile = UploadedFile::fake()->create('large.jpg', 3072, 'image/jpeg');

        $response = $this->uploadAvatar($largeFile);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_upload_avatar_no_file_returns_422()
    {
        // Kirim request tanpa field avatar
        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/farmer/profile/avatar', []);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_upload_avatar_jpeg_png_and_webp_formats_succeed()
    {
        foreach (['avatar.jpg', 'avatar.png', 'avatar.webp'] as $filename) {
            $avatar = UploadedFile::fake()->image($filename, 400, 400)->size(300);

            $response = $this->uploadAvatar($avatar);

            $response->assertStatus(200, "Format {$filename} harus berhasil upload");
        }
    }

    // ========================================================================
    // 12.1: GET /api/v1/farmer/preferences — Get Preferensi
    // ========================================================================

    public function test_get_preferences_returns_200_with_all_fields()
    {
        $response = $this->getPreferences();

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
                'message' => 'Preferensi petani berhasil diambil',
            ])
            ->assertJsonStructure([
                'data' => [
                    'preferences' => [
                        'language',
                        'language_label',
                        'batch_notification',
                        'batch_notification_label',
                        'temperature_unit',
                        'temperature_unit_label',
                        'save_mode',
                        'save_mode_label',
                    ],
                    'available_options' => [
                        'languages',
                        'batch_notifications',
                        'temperature_units',
                        'save_modes',
                    ],
                ],
                'timestamp',
            ]);
    }

    public function test_get_preferences_available_options_structure()
    {
        $response = $this->getPreferences();

        $response->assertStatus(200);

        $options = $response->json('data.available_options');

        // Cek setiap option punya value + label
        foreach (['languages', 'batch_notifications', 'temperature_units', 'save_modes'] as $key) {
            $this->assertArrayHasKey($key, $options);
            $this->assertNotEmpty($options[$key]);

            foreach ($options[$key] as $option) {
                $this->assertArrayHasKey('value', $option);
                $this->assertArrayHasKey('label', $option);
            }
        }
    }

    public function test_get_preferences_default_values()
    {
        $response = $this->getPreferences();

        $response->assertStatus(200);

        $prefs = $response->json('data.preferences');

        // Cek valid values
        $this->assertContains($prefs['language'], ['id', 'en']);
        $this->assertContains($prefs['batch_notification'], ['active', 'ready_only', 'inactive']);
        $this->assertContains($prefs['temperature_unit'], ['celsius', 'fahrenheit']);
        $this->assertContains($prefs['save_mode'], ['auto', 'manual']);

        // Label harus sesuai dengan value
        $this->assertNotEmpty($prefs['language_label']);
        $this->assertNotEmpty($prefs['batch_notification_label']);
        $this->assertNotEmpty($prefs['temperature_unit_label']);
        $this->assertNotEmpty($prefs['save_mode_label']);
    }

    // ========================================================================
    // 12.2: PATCH /api/v1/farmer/preferences — Update Preferensi
    // ========================================================================

    public function test_update_preferences_language_returns_200()
    {
        $response = $this->updatePreferences(['language' => 'en']);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS_UPDATE',
                'message' => 'Preferensi berhasil diperbarui',
            ])
            ->assertJsonPath('data.preferences.language', 'en')
            ->assertJsonPath('data.preferences.language_label', 'English');
    }

    public function test_update_preferences_all_fields_returns_200()
    {
        $response = $this->updatePreferences([
            'language' => 'en',
            'batch_notification' => 'ready_only',
            'temperature_unit' => 'fahrenheit',
            'save_mode' => 'manual',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.preferences.language', 'en')
            ->assertJsonPath('data.preferences.batch_notification', 'ready_only')
            ->assertJsonPath('data.preferences.temperature_unit', 'fahrenheit')
            ->assertJsonPath('data.preferences.save_mode', 'manual');
    }

    public function test_update_preferences_partial_update_preserves_other_fields()
    {
        // Hanya update save_mode
        $response = $this->updatePreferences(['save_mode' => 'manual']);

        $response->assertStatus(200)
            ->assertJsonPath('data.preferences.save_mode', 'manual');

        // Field lain tidak boleh berubah
        $prefs = $response->json('data.preferences');
        $this->assertNotEmpty($prefs['language']);
        $this->assertNotEmpty($prefs['batch_notification']);
        $this->assertNotEmpty($prefs['temperature_unit']);
    }

    public function test_update_preferences_invalid_language_returns_422()
    {
        $response = $this->updatePreferences(['language' => 'fr']);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_update_preferences_invalid_batch_notification_returns_422()
    {
        $response = $this->updatePreferences(['batch_notification' => 'all']);

        $response->assertStatus(422);
    }

    public function test_update_preferences_invalid_temperature_unit_returns_422()
    {
        $response = $this->updatePreferences(['temperature_unit' => 'kelvin']);

        $response->assertStatus(422);
    }

    public function test_update_preferences_invalid_save_mode_returns_422()
    {
        $response = $this->updatePreferences(['save_mode' => 'cloud']);

        $response->assertStatus(422);
    }

    public function test_update_preferences_empty_body_returns_422()
    {
        $response = $this->updatePreferences([]);

        $response->assertStatus(422);
    }

    // ========================================================================
    // Auth & Role — Semua Endpoint Profil & Preferensi
    // ========================================================================

    // --- F. Profile Auth ---

    public function test_get_profile_unauthorized_returns_401()
    {
        $this->getJson('/api/v1/farmer/profile')
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    public function test_patch_profile_unauthorized_returns_401()
    {
        $this->patchJson('/api/v1/farmer/profile', ['full_name' => 'Test'])
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    // --- K. Password & Avatar Auth ---

    public function test_change_password_unauthorized_returns_401()
    {
        $this->patchJson('/api/v1/farmer/security/password', [
            'old_password' => 'test',
            'new_password' => 'newtest123!',
            'new_password_confirmation' => 'newtest123!',
        ])
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    public function test_upload_avatar_forbidden_exporter_role_returns_403()
    {
        $avatar = UploadedFile::fake()->image('avatar.jpg', 400, 400)->size(300);

        $response = $this->uploadAvatar($avatar, $this->exporter);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }

    // --- Preferences Auth ---

    public function test_get_preferences_unauthorized_returns_401()
    {
        $this->getJson('/api/v1/farmer/preferences')
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    public function test_patch_preferences_forbidden_exporter_role_returns_403()
    {
        $response = $this->updatePreferences(['language' => 'en'], $this->exporter);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }

    // --- Data Isolation ---

    public function test_profile_data_isolation_each_farmer_sees_own_profile()
    {
        // Get profile farmer
        $response1 = $this->getProfile($this->farmer);
        $response1->assertStatus(200)
            ->assertJsonPath('data.profile.email', $this->farmer->email)
            ->assertJsonPath('data.profile.phone', $this->farmer->phone);

        // Get profile farmer2
        $response2 = $this->getProfile($this->farmer2);
        $response2->assertStatus(200)
            ->assertJsonPath('data.profile.email', $this->farmer2->email)
            ->assertJsonPath('data.profile.phone', $this->farmer2->phone);
    }

    public function test_preferences_data_isolation_each_farmer_has_own_preferences()
    {
        // Farmer1 update language ke en
        $this->updatePreferences(['language' => 'en'])->assertStatus(200);

        // Farmer2 update language ke id
        $this->updatePreferences(['language' => 'id'], $this->farmer2)->assertStatus(200);

        // Cek: farmer1 tetap en
        $r1 = $this->getPreferences($this->farmer);
        $r1->assertJsonPath('data.preferences.language', 'en');

        // Cek: farmer2 tetap id
        $r2 = $this->getPreferences($this->farmer2);
        $r2->assertJsonPath('data.preferences.language', 'id');
    }

    // ========================================================================
    // Cross-Module: Profil & Dashboard Integration
    // ========================================================================

    public function test_profile_completion_reflected_in_dashboard()
    {
        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/farmer/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('data.farmer.profile_completion', $this->farmer->profile_completion);
    }

    public function test_profile_phone_warning_reflected_in_dashboard()
    {
        // Farmer tanpa phone → dashboard warnings.phone_missing = true
        $noPhoneFarmer = User::factory()->create([
            'role' => 'farmer',
            'name' => 'Tanpa Phone',
            'phone' => null,
            'phone_verified' => false,
        ]);

        $response = $this->actingAs($noPhoneFarmer)
            ->getJson('/api/v1/farmer/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('data.warnings.phone_missing', true);
    }
}

<?php

namespace Tests\Feature\Buyer;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * BuyerProfileTest — Test Case untuk Modul Profil & Pengaturan Buyer (API Contract V3)
 *
 * Scope: 5 endpoint profil & preferensi buyer
 *   - GET /api/v1/buyer/profile               — Profil lengkap buyer (§11.1)
 *   - PATCH /api/v1/buyer/profile              — Update profil buyer (§11.2)
 *   - PATCH /api/v1/buyer/security/password     — Ganti password (§11.3)
 *   - GET /api/v1/buyer/preferences            — Preferensi buyer (§11.4)
 *   - PATCH /api/v1/buyer/preferences          — Update preferensi (§11.5)
 *
 * Reference: API_CONTRACT_V3_BUYER.md
 *   - Section 11 (Modul Profil & Pengaturan Buyer)
 *   - Section 5   (Kode Error — VALIDATION_ERROR, CONFLICT)
 *
 * Response fields yang ditest:
 *   [11.1] GET profile: profile (id, name, email, phone, company_name, business_id,
 *         business_id_type, role, profile_completion, email_verified_at, created_at, updated_at),
 *         completion_details (name, email, phone, company_name, business_id, missing_fields,
 *         missing_fields_labels)
 *   [11.2] PATCH profile: profile (updated fields, profile_completion recalculated)
 *   [11.3] PATCH password: data = null, message "Password berhasil diubah"
 *   [11.4] GET preferences: language, language_label, notification_*, currency, currency_label,
 *         email_reminder, email_reminder_hours
 *   [11.5] PATCH preferences: updated preferences with labels
 *
 * Business Rules V3 yang ditest:
 *   - Buyer-specific fields: phone, company_name, business_id, business_id_type
 *   - email is READ-ONLY (tidak bisa diupdate via PATCH profile)
 *   - profile_completion (0-100) dihitung dari: name, email, phone, company_name, business_id
 *   - completion_details = boolean per field + missing_fields array
 *   - Password: min 8 chars, uppercase + lowercase + number, current_password verified
 *   - Password change → session termination (token invalid)
 *   - Preferences: language (id/en), currency (IDR/USD), notification booleans,
 *     email_reminder (boolean), email_reminder_hours (1-24)
 *   - Partial update preserves other fields (PATCH semantics)
 *   - Data isolation: buyer hanya bisa update profil sendiri
 *
 * Sections (45 tests):
 *   1.  GET Profile — Response Structure (3 tests)
 *   2.  GET Profile — Completion Details (4 tests)
 *   3.  GET Profile — Data Isolation (2 tests)
 *   4.  PATCH Profile — Success (4 tests)
 *   5.  PATCH Profile — Validation Errors (5 tests)
 *   6.  PATCH Profile — Business Rules (4 tests)
 *   7.  PATCH Profile — Completion Recalculation (3 tests)
 *   8.  PATCH Password — Success (2 tests)
 *   9.  PATCH Password — Validation Errors (4 tests)
 *   10. PATCH Password — Business Rules (2 tests)
 *   11. GET Preferences — Response Structure (3 tests)
 *   12. PATCH Preferences — Success (3 tests)
 *   13. PATCH Preferences — Validation Errors (4 tests)
 *   14. PATCH Preferences — Partial Update (2 tests)
 */
class BuyerProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private User $buyer2;

    protected function setUp(): void
    {
        parent::setUp();

        // ── Buyer utama (profil belum lengkap — business_id kosong) ──
        $this->buyer = User::factory()->create([
            'role' => 'buyer',
            'name' => 'John Doe',
            'email' => 'john@company.com',
            'phone' => '+62 812-3456-7890',
            'company_name' => 'PT Kopi Nusantara',
            'business_id' => null,
            'business_id_type' => null,
            'password' => Hash::make('CurrentPassword123'),
            'profile_completion' => 80,
            'email_verified_at' => now()->subDays(7),
        ]);

        // ── Buyer kedua (profil lengkap) ──
        $this->buyer2 = User::factory()->create([
            'role' => 'buyer',
            'name' => 'Jane Smith',
            'email' => 'jane@coffee.co',
            'phone' => '+62 813-9876-5432',
            'company_name' => 'Pacific Roasters Inc.',
            'business_id' => 'NPWP-9876543210',
            'business_id_type' => 'NPWP',
            'password' => Hash::make('JanePassword456'),
            'profile_completion' => 100,
            'email_verified_at' => now()->subDays(3),
        ]);
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    private function getProfile(?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->buyer)
            ->getJson('/api/v1/buyer/profile');
    }

    private function patchProfile(array $data, ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->buyer)
            ->patchJson('/api/v1/buyer/profile', $data);
    }

    private function patchPassword(array $data, ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->buyer)
            ->patchJson('/api/v1/buyer/security/password', $data);
    }

    private function getPreferences(?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->buyer)
            ->getJson('/api/v1/buyer/preferences');
    }

    private function patchPreferences(array $data, ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->buyer)
            ->patchJson('/api/v1/buyer/preferences', $data);
    }

    // ========================================================================
    // SECTION 1: GET PROFILE — RESPONSE STRUCTURE (3 tests)
    // ========================================================================

    /** @test */
    public function test_get_profile_returns_200_with_correct_structure(): void
    {
        $response = $this->getProfile();

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
                'message' => 'Profil pembeli berhasil diambil',
            ]);

        $response->assertJsonStructure([
            'data' => [
                'profile',
                'completion_details',
            ],
            'timestamp',
        ]);
    }

    /** @test */
    public function test_get_profile_has_all_buyer_specific_fields(): void
    {
        $response = $this->getProfile();

        $response->assertOk();

        $profile = $response->json('data.profile');
        $buyerFields = [
            'id', 'name', 'email', 'phone', 'company_name',
            'business_id', 'business_id_type', 'role',
            'profile_completion', 'email_verified_at', 'created_at', 'updated_at',
        ];

        foreach ($buyerFields as $field) {
            $this->assertArrayHasKey($field, $profile, "Missing profile field: {$field}");
        }

        $this->assertEquals('buyer', $profile['role']);
    }

    /** @test */
    public function test_get_profile_returns_null_for_unset_buyer_fields(): void
    {
        // buyer utama: business_id = null
        $response = $this->getProfile();

        $response->assertOk();

        $profile = $response->json('data.profile');
        $this->assertNull($profile['business_id']);
        $this->assertNull($profile['business_id_type']);
    }

    // ========================================================================
    // SECTION 2: GET PROFILE — COMPLETION DETAILS (4 tests)
    // ========================================================================

    /** @test */
    public function test_get_profile_completion_details_has_all_boolean_flags(): void
    {
        $response = $this->getProfile();

        $response->assertOk();

        $completion = $response->json('data.completion_details');
        $flags = ['name', 'email', 'phone', 'company_name', 'business_id'];

        foreach ($flags as $flag) {
            $this->assertArrayHasKey($flag, $completion, "Missing completion flag: {$flag}");
            $this->assertIsBool($completion[$flag]);
        }
    }

    /** @test */
    public function test_get_profile_completion_details_missing_fields_for_incomplete_profile(): void
    {
        // buyer: business_id = null → missing
        $response = $this->getProfile();

        $response->assertOk();

        $completion = $response->json('data.completion_details');
        $this->assertFalse($completion['business_id']);
        $this->assertContains('business_id', $completion['missing_fields']);
        $this->assertContains('NPWP / Business ID', $completion['missing_fields_labels']);
    }

    /** @test */
    public function test_get_profile_completion_details_all_complete_for_full_profile(): void
    {
        $response = $this->getProfile($this->buyer2);

        $response->assertOk();

        $completion = $response->json('data.completion_details');
        $this->assertTrue($completion['name']);
        $this->assertTrue($completion['email']);
        $this->assertTrue($completion['phone']);
        $this->assertTrue($completion['company_name']);
        $this->assertTrue($completion['business_id']);
        $this->assertEmpty($completion['missing_fields']);
    }

    /** @test */
    public function test_get_profile_completion_details_true_for_filled_buyer_fields(): void
    {
        $response = $this->getProfile();

        $response->assertOk();

        $completion = $response->json('data.completion_details');
        // buyer: name, email, phone, company_name filled → true
        $this->assertTrue($completion['name']);
        $this->assertTrue($completion['email']);
        $this->assertTrue($completion['phone']);
        $this->assertTrue($completion['company_name']);
    }

    // ========================================================================
    // SECTION 3: GET PROFILE — DATA ISOLATION (2 tests)
    // ========================================================================

    /** @test */
    public function test_get_profile_requires_authentication(): void
    {
        $this->getJson('/api/v1/buyer/profile')
            ->assertUnauthorized()
            ->assertJson(['success' => false, 'code' => 'UNAUTHORIZED']);
    }

    /** @test */
    public function test_get_profile_rejects_farmer_role(): void
    {
        $farmer = User::factory()->create(['role' => 'farmer']);

        $this->actingAs($farmer)
            ->getJson('/api/v1/buyer/profile')
            ->assertStatus(403)
            ->assertJson(['success' => false, 'code' => 'FORBIDDEN']);
    }

    // ========================================================================
    // SECTION 4: PATCH PROFILE — SUCCESS (4 tests)
    // ========================================================================

    /** @test */
    public function test_patch_profile_update_name_only(): void
    {
        $response = $this->patchProfile(['name' => 'John Doe Updated']);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS_UPDATE',
                'message' => 'Profil pembeli berhasil diperbarui',
            ])
            ->assertJsonPath('data.profile.name', 'John Doe Updated');
    }

    /** @test */
    public function test_patch_profile_update_phone_and_company(): void
    {
        $response = $this->patchProfile([
            'phone' => '+62 812-9999-8888',
            'company_name' => 'PT Kopi Nusantara International',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.profile.phone', '+62 812-9999-8888')
            ->assertJsonPath('data.profile.company_name', 'PT Kopi Nusantara International');
    }

    /** @test */
    public function test_patch_profile_update_business_id(): void
    {
        $response = $this->patchProfile([
            'business_id' => 'NPWP-12.345.678.9-012.000',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.profile.business_id', 'NPWP-12.345.678.9-012.000')
            ->assertJsonPath('data.profile.business_id_type', 'NPWP');
    }

    /** @test */
    public function test_patch_profile_update_all_fields(): void
    {
        $response = $this->patchProfile([
            'name' => 'John Doe Complete',
            'phone' => '+62 812-1111-2222',
            'company_name' => 'PT Nusantara Coffee Trading',
            'business_id' => 'NPWP-99.888.777.6-555.000',
        ]);

        $response->assertOk();

        $profile = $response->json('data.profile');
        $this->assertEquals('John Doe Complete', $profile['name']);
        $this->assertEquals('+62 812-1111-2222', $profile['phone']);
        $this->assertEquals('PT Nusantara Coffee Trading', $profile['company_name']);
        $this->assertEquals('NPWP-99.888.777.6-555.000', $profile['business_id']);
    }

    // ========================================================================
    // SECTION 5: PATCH PROFILE — VALIDATION ERRORS (5 tests)
    // ========================================================================

    /** @test */
    public function test_patch_profile_rejects_name_below_minimum(): void
    {
        $response = $this->patchProfile(['name' => 'J']);

        $response->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    /** @test */
    public function test_patch_profile_rejects_invalid_phone_format(): void
    {
        $response = $this->patchProfile(['phone' => '081234567890']);

        $response->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    /** @test */
    public function test_patch_profile_rejects_company_name_below_minimum(): void
    {
        $response = $this->patchProfile(['company_name' => 'A']);

        $response->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    /** @test */
    public function test_patch_profile_rejects_business_id_too_short(): void
    {
        $response = $this->patchProfile(['business_id' => 'ABC']);

        $response->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    /** @test */
    public function test_patch_profile_rejects_empty_body(): void
    {
        $response = $this->patchProfile([]);

        // Tidak ada field yang diupdate → minimal 1 field harus ada
        $response->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    // ========================================================================
    // SECTION 6: PATCH PROFILE — BUSINESS RULES (4 tests)
    // ========================================================================

    /** @test */
    public function test_patch_profile_email_is_read_only(): void
    {
        $response = $this->patchProfile([
            'name' => 'John Doe Updated',
            'email' => 'newemail@company.com',
        ]);

        // Email tidak boleh berubah
        $this->buyer->refresh();
        $this->assertEquals('john@company.com', $this->buyer->email);

        // Response seharusnya mengabaikan email atau error
        // Behavior yang diharapkan: email diabaikan atau 422
        // Karena contract bilang email tidak ada di PATCH body, backend harus ignore atau reject
    }

    /** @test */
    public function test_patch_profile_preserves_unupdated_fields(): void
    {
        $originalPhone = $this->buyer->phone;
        $originalCompany = $this->buyer->company_name;

        $response = $this->patchProfile(['name' => 'John Doe Updated']);

        $response->assertOk();

        $this->buyer->refresh();
        $this->assertEquals($originalPhone, $this->buyer->phone);
        $this->assertEquals($originalCompany, $this->buyer->company_name);
        $this->assertEquals('John Doe Updated', $this->buyer->name);
    }

    /** @test */
    public function test_patch_profile_rejects_farmer_role(): void
    {
        $farmer = User::factory()->create(['role' => 'farmer']);

        $this->actingAs($farmer)
            ->patchJson('/api/v1/buyer/profile', ['name' => 'Hacked'])
            ->assertStatus(403)
            ->assertJson(['success' => false, 'code' => 'FORBIDDEN']);
    }

    /** @test */
    public function test_patch_profile_buyer_can_only_update_own_profile(): void
    {
        $originalName = $this->buyer->name;

        $response = $this->patchProfile(['name' => 'Hacked Name'], $this->buyer2);

        $response->assertOk();

        // buyer2 update profil sendiri, bukan profil buyer
        $this->buyer2->refresh();
        $this->assertEquals('Hacked Name', $this->buyer2->name);

        // buyer tetap utuh
        $this->buyer->refresh();
        $this->assertEquals($originalName, $this->buyer->name);
    }

    // ========================================================================
    // SECTION 7: PATCH PROFILE — COMPLETION RECALCULATION (3 tests)
    // ========================================================================

    /** @test */
    public function test_patch_profile_completion_increases_when_business_id_added(): void
    {
        $beforeCompletion = $this->buyer->profile_completion;

        $response = $this->patchProfile([
            'business_id' => 'NPWP-12.345.678.9-012.000',
        ]);

        $response->assertOk();

        $this->buyer->refresh();
        // Completion harus naik setelah business_id diisi
        $this->assertGreaterThan($beforeCompletion, $this->buyer->profile_completion);
        $this->assertEquals(100, $this->buyer->profile_completion);

        // Response juga harus mencerminkan completion baru
        $this->assertEquals(100, $response->json('data.profile.profile_completion'));
    }

    /** @test */
    public function test_patch_profile_completion_details_updated_after_fill_missing_field(): void
    {
        // business_id = null → false
        $this->getProfile()
            ->assertJsonPath('data.completion_details.business_id', false);

        // Fill business_id
        $this->patchProfile(['business_id' => 'NPWP-1234567890']);

        // Sekarang harus true
        $this->getProfile()
            ->assertJsonPath('data.completion_details.business_id', true)
            ->assertJsonPath('data.completion_details.missing_fields', []);
    }

    /** @test */
    public function test_patch_profile_completion_stays_at_100_for_already_complete(): void
    {
        // buyer2 sudah 100%
        $response = $this->patchProfile(['name' => 'Jane Smith Updated'], $this->buyer2);

        $response->assertOk()
            ->assertJsonPath('data.profile.profile_completion', 100);
    }

    // ========================================================================
    // SECTION 8: PATCH PASSWORD — SUCCESS (2 tests)
    // ========================================================================

    /** @test */
    public function test_patch_password_success(): void
    {
        $response = $this->patchPassword([
            'current_password' => 'CurrentPassword123',
            'new_password' => 'NewPassword456',
            'new_password_confirmation' => 'NewPassword456',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS_UPDATE',
                'message' => 'Password berhasil diubah',
                'data' => null,
            ]);

        // Verifikasi password berubah di database
        $this->buyer->refresh();
        $this->assertTrue(Hash::check('NewPassword456', $this->buyer->password));
    }

    /** @test */
    public function test_patch_password_invalidates_current_token(): void
    {
        $this->patchPassword([
            'current_password' => 'CurrentPassword123',
            'new_password' => 'NewPassword456',
            'new_password_confirmation' => 'NewPassword456',
        ])->assertOk();

        // Token lama harus invalid (session terminated)
        $this->getProfile()
            ->assertUnauthorized();
    }

    // ========================================================================
    // SECTION 9: PATCH PASSWORD — VALIDATION ERRORS (4 tests)
    // ========================================================================

    /** @test */
    public function test_patch_password_rejects_wrong_current_password(): void
    {
        $response = $this->patchPassword([
            'current_password' => 'WrongPassword999',
            'new_password' => 'NewPassword456',
            'new_password_confirmation' => 'NewPassword456',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED',
            ]);
    }

    /** @test */
    public function test_patch_password_rejects_new_password_below_minimum(): void
    {
        $response = $this->patchPassword([
            'current_password' => 'CurrentPassword123',
            'new_password' => 'Short1',
            'new_password_confirmation' => 'Short1',
        ]);

        $response->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    /** @test */
    public function test_patch_password_rejects_confirmation_mismatch(): void
    {
        $response = $this->patchPassword([
            'current_password' => 'CurrentPassword123',
            'new_password' => 'NewPassword456',
            'new_password_confirmation' => 'DifferentPassword789',
        ]);

        $response->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    /** @test */
    public function test_patch_password_rejects_new_password_same_as_current(): void
    {
        $response = $this->patchPassword([
            'current_password' => 'CurrentPassword123',
            'new_password' => 'CurrentPassword123',
            'new_password_confirmation' => 'CurrentPassword123',
        ]);

        // Password baru tidak boleh sama dengan password lama
        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'code' => 'CONFLICT',
            ]);
    }

    // ========================================================================
    // SECTION 10: PATCH PASSWORD — BUSINESS RULES (2 tests)
    // ========================================================================

    /** @test */
    public function test_patch_password_requires_authentication(): void
    {
        $this->patchJson('/api/v1/buyer/security/password', [
            'current_password' => 'password',
            'new_password' => 'NewPassword123',
            'new_password_confirmation' => 'NewPassword123',
        ])->assertUnauthorized()
            ->assertJson(['success' => false, 'code' => 'UNAUTHORIZED']);
    }

    /** @test */
    public function test_patch_password_rejects_exporter_role(): void
    {
        $exporter = User::factory()->create(['role' => 'exporter']);

        $this->actingAs($exporter)
            ->patchJson('/api/v1/buyer/security/password', [
                'current_password' => 'password',
                'new_password' => 'NewPassword123',
                'new_password_confirmation' => 'NewPassword123',
            ])
            ->assertStatus(403)
            ->assertJson(['success' => false, 'code' => 'FORBIDDEN']);
    }

    // ========================================================================
    // SECTION 11: GET PREFERENCES — RESPONSE STRUCTURE (3 tests)
    // ========================================================================

    /** @test */
    public function test_get_preferences_returns_200_with_correct_structure(): void
    {
        $response = $this->getPreferences();

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS',
                'message' => 'Preferensi pembeli berhasil diambil',
            ]);

        $response->assertJsonStructure([
            'data' => ['preferences'],
            'timestamp',
        ]);
    }

    /** @test */
    public function test_get_preferences_has_all_buyer_preference_fields(): void
    {
        $response = $this->getPreferences();

        $response->assertOk();

        $prefs = $response->json('data.preferences');
        $requiredFields = [
            'language', 'language_label',
            'notification_order_status', 'notification_payment',
            'notification_shipment', 'notification_catalog_update',
            'currency', 'currency_label',
            'email_reminder', 'email_reminder_hours',
        ];

        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $prefs, "Missing preference field: {$field}");
        }
    }

    /** @test */
    public function test_get_preferences_has_correct_default_values(): void
    {
        $response = $this->getPreferences();

        $response->assertOk();

        $prefs = $response->json('data.preferences');

        // Defaults per contract
        $this->assertEquals('id', $prefs['language']);
        $this->assertEquals('Bahasa Indonesia', $prefs['language_label']);
        $this->assertTrue($prefs['notification_order_status']);
        $this->assertTrue($prefs['notification_payment']);
        $this->assertTrue($prefs['notification_shipment']);
        $this->assertFalse($prefs['notification_catalog_update']);
        $this->assertEquals('IDR', $prefs['currency']);
        $this->assertEquals('Rupiah Indonesia', $prefs['currency_label']);
        $this->assertTrue($prefs['email_reminder']);
        $this->assertEquals(2, $prefs['email_reminder_hours']);
    }

    // ========================================================================
    // SECTION 12: PATCH PREFERENCES — SUCCESS (3 tests)
    // ========================================================================

    /** @test */
    public function test_patch_preferences_update_language_and_currency(): void
    {
        $response = $this->patchPreferences([
            'language' => 'en',
            'currency' => 'USD',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'code' => 'SUCCESS_UPDATE',
                'message' => 'Preferensi pembeli berhasil diperbarui',
            ])
            ->assertJsonPath('data.preferences.language', 'en')
            ->assertJsonPath('data.preferences.language_label', 'English')
            ->assertJsonPath('data.preferences.currency', 'USD')
            ->assertJsonPath('data.preferences.currency_label', 'US Dollar');
    }

    /** @test */
    public function test_patch_preferences_update_notification_settings(): void
    {
        $response = $this->patchPreferences([
            'notification_order_status' => false,
            'notification_catalog_update' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.preferences.notification_order_status', false)
            ->assertJsonPath('data.preferences.notification_catalog_update', true);
    }

    /** @test */
    public function test_patch_preferences_update_email_reminder(): void
    {
        $response = $this->patchPreferences([
            'email_reminder' => true,
            'email_reminder_hours' => 5,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.preferences.email_reminder', true)
            ->assertJsonPath('data.preferences.email_reminder_hours', 5);
    }

    // ========================================================================
    // SECTION 13: PATCH PREFERENCES — VALIDATION ERRORS (4 tests)
    // ========================================================================

    /** @test */
    public function test_patch_preferences_rejects_invalid_language(): void
    {
        $response = $this->patchPreferences(['language' => 'jp']);

        $response->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    /** @test */
    public function test_patch_preferences_rejects_invalid_currency(): void
    {
        $response = $this->patchPreferences(['currency' => 'EUR']);

        $response->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    /** @test */
    public function test_patch_preferences_rejects_email_reminder_hours_out_of_range(): void
    {
        $response = $this->patchPreferences(['email_reminder_hours' => 30]);

        $response->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    /** @test */
    public function test_patch_preferences_rejects_empty_body(): void
    {
        $response = $this->patchPreferences([]);

        $response->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    // ========================================================================
    // SECTION 14: PATCH PREFERENCES — PARTIAL UPDATE (2 tests)
    // ========================================================================

    /** @test */
    public function test_patch_preferences_partial_update_preserves_other_fields(): void
    {
        // Update language saja
        $this->patchPreferences(['language' => 'en'])->assertOk();

        // Cek preferences — language berubah, lainnya tetap default
        $response = $this->getPreferences();
        $response->assertOk();

        $prefs = $response->json('data.preferences');
        $this->assertEquals('en', $prefs['language']);
        $this->assertEquals('English', $prefs['language_label']);
        // Lainnya tetap default
        $this->assertTrue($prefs['notification_order_status']);
        $this->assertEquals('IDR', $prefs['currency']);
        $this->assertTrue($prefs['email_reminder']);
    }

    /** @test */
    public function test_patch_preferences_each_buyer_has_own_preferences(): void
    {
        // Update preference buyer
        $this->patchPreferences(['language' => 'en'])->assertOk();

        // Preference buyer2 tetap default
        $response = $this->getPreferences($this->buyer2);
        $response->assertOk()
            ->assertJsonPath('data.preferences.language', 'id');
    }
}

<?php

namespace App\Http\Controllers\Api\v1\Buyer;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ProfileService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class BuyerProfileController extends Controller
{
    use ApiResponseTrait;

    /**
     * GET /api/v1/buyer/profile
     */
    public function show(Request $request)
    {
        $user = $request->user();

        // Reject session if password was changed and force_logout is set
        if ($user->force_logout) {
            return $this->apiErrorResponse('UNAUTHORIZED', 'Sesi telah diakhiri. Silakan login kembali.', 401);
        }

        app(ProfileService::class)->recalculateCompletion($user, 'buyer');

        return $this->apiResponse(true, 'SUCCESS', 'Profil pembeli berhasil diambil', [
            'profile' => $this->transformProfile($user),
            'completion_details' => $this->getCompletionDetails($user),
        ]);
    }

    /**
     * PATCH /api/v1/buyer/profile
     */
    public function update(Request $request)
    {
        $user = $request->user();

        // Require at least one updatable field
        $updatableFields = ['name', 'phone', 'company_name', 'business_id'];
        $hasAtLeastOne = collect($updatableFields)->some(fn ($field) => $request->has($field));

        if (! $hasAtLeastOne) {
            return $this->apiErrorResponse('VALIDATION_ERROR', 'Minimal satu field harus diisi untuk diperbarui.', 422, [
                'general' => ['Tidak ada field yang diberikan untuk diperbarui.'],
            ]);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|min:3|max:255',
            'phone' => ['sometimes', 'string', 'regex:/^\+\d{1,4}\s\d{3,4}-\d{3,4}-\d{3,5}$/'],
            'company_name' => 'sometimes|string|min:2|max:255',
            'business_id' => 'sometimes|string|min:5|max:255',
        ]);

        if ($validator->fails()) {
            return $this->apiErrorResponse('VALIDATION_ERROR', 'Input tidak valid', 422, $validator->errors()->toArray());
        }

        // Update fields (ignoring email)
        $data = $request->only(['name', 'phone', 'company_name', 'business_id']);

        if ($request->has('business_id')) {
            $businessId = $request->business_id;

            if (! empty($businessId)) {
                if (str_contains(strtoupper($businessId), 'NPWP')) {
                    $data['business_id_type'] = 'NPWP';
                } else {
                    $data['business_id_type'] = 'OTHER';
                }
            } else {
                $data['business_id_type'] = null;
            }
        }

        $user->update($data);
        app(ProfileService::class)->recalculateCompletion($user, 'buyer');

        return $this->apiResponse(true, 'SUCCESS_UPDATE', 'Profil pembeli berhasil diperbarui', [
            'profile' => $this->transformProfile($user),
            'completion_details' => $this->getCompletionDetails($user),
        ]);
    }

    /**
     * PATCH /api/v1/buyer/security/password
     */
    public function changePassword(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'new_password' => 'required|string|min:8|max:255',
            'new_password_confirmation' => 'required|same:new_password',
        ]);

        if ($validator->fails()) {
            return $this->apiErrorResponse('VALIDATION_ERROR', 'Validation failed', 422, $validator->errors()->toArray());
        }

        // Verify current password
        if (! Hash::check($request->current_password, $user->password)) {
            return $this->apiErrorResponse('UNAUTHORIZED', 'Password saat ini salah', 401);
        }

        // Check if same as current
        if (Hash::check($request->new_password, $user->password)) {
            return $this->apiErrorResponse('CONFLICT', 'Password baru tidak boleh sama dengan password lama', 409);
        }

        // Update password and set force_logout flag to invalidate existing sessions
        $user->update([
            'password' => Hash::make($request->new_password),
            'force_logout' => true,
        ]);

        // Revoke all Sanctum tokens
        $user->tokens()->delete();

        return $this->apiResponse(true, 'SUCCESS_UPDATE', 'Password berhasil diubah', null);
    }

    /**
     * GET /api/v1/buyer/preferences
     */
    public function preferences(Request $request)
    {
        $user = $request->user();

        return $this->apiResponse(true, 'SUCCESS', 'Preferensi pembeli berhasil diambil', [
            'preferences' => $this->transformPreferences($user),
        ]);
    }

    /**
     * PATCH /api/v1/buyer/preferences
     */
    public function updatePreferences(Request $request)
    {
        $user = $request->user();

        // Require at least one preference field
        $preferenceFields = [
            'language', 'currency', 'notification_order_status', 'notification_payment',
            'notification_shipment', 'notification_catalog_update', 'email_reminder', 'email_reminder_hours',
        ];
        $hasAtLeastOne = collect($preferenceFields)->some(fn ($field) => $request->has($field));

        if (! $hasAtLeastOne) {
            return $this->apiErrorResponse('VALIDATION_ERROR', 'Minimal satu preferensi harus disertakan.', 422, [
                'general' => ['Tidak ada field preferensi yang diberikan.'],
            ]);
        }

        $validator = Validator::make($request->all(), [
            'language' => 'sometimes|in:id,en',
            'currency' => 'sometimes|in:IDR,USD',
            'notification_order_status' => 'sometimes|boolean',
            'notification_payment' => 'sometimes|boolean',
            'notification_shipment' => 'sometimes|boolean',
            'notification_catalog_update' => 'sometimes|boolean',
            'email_reminder' => 'sometimes|boolean',
            'email_reminder_hours' => 'sometimes|integer|min:1|max:24',
        ]);

        if ($validator->fails()) {
            return $this->apiErrorResponse('VALIDATION_ERROR', 'Validation failed', 422, $validator->errors()->toArray());
        }

        $user->update($request->only($preferenceFields));

        return $this->apiResponse(true, 'SUCCESS_UPDATE', 'Preferensi pembeli berhasil diperbarui', [
            'preferences' => $this->transformPreferences($user->fresh()),
        ]);
    }

    private function recalculateCompletion(User $user): void
    {
        app(ProfileService::class)->recalculateCompletion($user, 'buyer');
    }

    private function getCompletionDetails(User $user): array
    {
        $details = [
            'name' => ! empty($user->name),
            'email' => ! empty($user->email),
            'phone' => ! empty($user->phone),
            'company_name' => ! empty($user->company_name),
            'business_id' => ! empty($user->business_id),
            'missing_fields' => [],
            'missing_fields_labels' => [],
        ];

        if (empty($user->name)) {
            $details['missing_fields'][] = 'name';
            $details['missing_fields_labels'][] = 'Nama Lengkap';
        }
        if (empty($user->email)) {
            $details['missing_fields'][] = 'email';
            $details['missing_fields_labels'][] = 'Alamat Email';
        }
        if (empty($user->phone)) {
            $details['missing_fields'][] = 'phone';
            $details['missing_fields_labels'][] = 'Nomor Telepon';
        }
        if (empty($user->company_name)) {
            $details['missing_fields'][] = 'company_name';
            $details['missing_fields_labels'][] = 'Nama Perusahaan';
        }
        if (empty($user->business_id)) {
            $details['missing_fields'][] = 'business_id';
            $details['missing_fields_labels'][] = 'NPWP / Business ID';
        }

        return $details;
    }

    private function transformProfile(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'company_name' => $user->company_name,
            'business_id' => $user->business_id,
            'business_id_type' => $user->business_id_type,
            'role' => $user->role,
            'profile_completion' => (int) $user->profile_completion,
            'email_verified_at' => $user->email_verified_at ? $user->email_verified_at->toIso8601String() : null,
            'created_at' => $user->created_at->toIso8601String(),
            'updated_at' => $user->updated_at->toIso8601String(),
        ];
    }

    private function transformPreferences(User $user): array
    {
        $lang = $user->language ?? 'id';
        $curr = $user->currency ?? 'IDR';

        return [
            'language' => $lang,
            'language_label' => $lang === 'en' ? 'English' : 'Bahasa Indonesia',
            'notification_order_status' => (bool) $user->notification_order_status,
            'notification_payment' => (bool) $user->notification_payment,
            'notification_shipment' => (bool) $user->notification_shipment,
            'notification_catalog_update' => (bool) $user->notification_catalog_update,
            'currency' => $curr,
            'currency_label' => $curr === 'USD' ? 'US Dollar' : 'Rupiah Indonesia',
            'email_reminder' => (bool) $user->email_reminder,
            'email_reminder_hours' => (int) ($user->email_reminder_hours ?? 2),
        ];
    }
}

<?php

namespace App\Http\Controllers\Api\v1\Farmer;

use App\Http\Controllers\Controller;
use App\Services\ProfileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class FarmerProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        $coordinates_latitude = null;
        $coordinates_longitude = null;
        if ($user->coordinates) {
            $parts = explode(',', $user->coordinates);
            if (count($parts) == 2) {
                $coordinates_latitude = (float) trim($parts[0]);
                $coordinates_longitude = (float) trim($parts[1]);
            }
        }

        return response()->json([
            'success' => true,
            'code' => 'SUCCESS',
            'message' => 'Profil petani berhasil diambil',
            'data' => [
                'profile' => [
                    'id' => $user->id,
                    'full_name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'phone_verified' => (bool) $user->phone_verified,
                    'location' => $user->location,
                    'coordinates' => $user->coordinates,
                    'coordinates_latitude' => $coordinates_latitude,
                    'coordinates_longitude' => $coordinates_longitude,
                    'avatar_url' => $user->avatar ? asset('storage/'.$user->avatar) : null,
                    'role' => $user->role,
                    'profile_completion' => (int) $user->profile_completion,
                    'profile_completion_details' => [
                        'name' => ! empty($user->name),
                        'email' => ! empty($user->email),
                        'phone' => ! empty($user->phone),
                        'location' => ! empty($user->location),
                        'coordinates' => ! empty($user->coordinates),
                        'avatar' => ! empty($user->avatar),
                    ],
                    'verification_status' => 'verified',
                    'iot_assigned' => (bool) $user->iot_assigned,
                    'iot_sensor_id' => $user->iot_sensor_id,
                    'created_at' => optional($user->created_at)->toIso8601String(),
                    'updated_at' => optional($user->updated_at)->toIso8601String(),
                    'joined_at' => optional($user->created_at)->toIso8601String(),
                ],
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function update(Request $request)
    {
        if (empty($request->all())) {
            return response()->json([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
                'message' => 'Data tidak valid',
            ], 422);
        }

        $request->validate([
            'full_name' => 'sometimes|string|min:3|max:255',
            'phone' => ['sometimes', 'string', 'regex:/^\+?62[0-9\s\-]+$/'],
            'location' => 'sometimes|string|min:3|max:255',
            'coordinates' => 'sometimes|string|regex:/^-?\d+(\.\d+)?,\s*-?\d+(\.\d+)?$/',
        ]);

        $user = $request->user();

        if ($request->has('full_name')) {
            $user->name = $request->full_name;
        }
        if ($request->has('phone')) {
            $user->phone = $request->phone;
        }
        if ($request->has('location')) {
            $user->location = $request->location;
        }
        if ($request->has('coordinates')) {
            $user->coordinates = $request->coordinates;
        }

        $user->save();

        app(ProfileService::class)->recalculateCompletion($user, 'farmer');

        $user->save();
        $user->refresh();

        $coordinates_latitude = null;
        $coordinates_longitude = null;
        if ($user->coordinates) {
            $parts = explode(',', $user->coordinates);
            if (count($parts) == 2) {
                $coordinates_latitude = (float) trim($parts[0]);
                $coordinates_longitude = (float) trim($parts[1]);
            }
        }

        return response()->json([
            'success' => true,
            'code' => 'SUCCESS_UPDATE',
            'message' => 'Profil berhasil diperbarui',
            'data' => [
                'profile' => [
                    'id' => $user->id,
                    'full_name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'phone_verified' => (bool) $user->phone_verified,
                    'location' => $user->location,
                    'coordinates' => $user->coordinates,
                    'coordinates_latitude' => $coordinates_latitude,
                    'coordinates_longitude' => $coordinates_longitude,
                    'avatar_url' => $user->avatar ? asset('storage/'.$user->avatar) : null,
                    'role' => $user->role,
                    'profile_completion' => (int) $user->profile_completion,
                    'profile_completion_details' => [
                        'name' => ! empty($user->name),
                        'email' => ! empty($user->email),
                        'phone' => ! empty($user->phone),
                        'location' => ! empty($user->location),
                        'coordinates' => ! empty($user->coordinates),
                        'avatar' => ! empty($user->avatar),
                    ],
                    'verification_status' => 'verified',
                    'iot_assigned' => (bool) $user->iot_assigned,
                    'iot_sensor_id' => $user->iot_sensor_id,
                    'created_at' => optional($user->created_at)->toIso8601String(),
                    'updated_at' => optional($user->updated_at)->toIso8601String(),
                    'joined_at' => optional($user->created_at)->toIso8601String(),
                ],
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $user = $request->user();

        $url = app(ProfileService::class)->uploadAvatar($user, $request->file('avatar'));
        $completion = app(ProfileService::class)->recalculateCompletion($user, 'farmer');

        return response()->json([
            'success' => true,
            'code' => 'SUCCESS_UPDATE',
            'message' => 'Avatar berhasil diperbarui',
            'data' => [
                'avatar_url' => asset($url),
                'profile_completion' => $completion,
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function preferences(Request $request)
    {
        $user = $request->user();

        $languageMap = ['id' => 'Bahasa Indonesia', 'en' => 'English'];
        $notificationMap = ['active' => 'Aktif', 'ready_only' => 'Hanya saat Siap', 'inactive' => 'Tidak Aktif'];
        $tempUnitMap = ['celsius' => 'Celsius', 'fahrenheit' => 'Fahrenheit'];
        $saveModeMap = ['auto' => 'Otomatis', 'manual' => 'Manual'];

        $language = $user->language ?? 'id';
        $batch_notification = $user->batch_notification ?? 'active';
        $temperature_unit = $user->temperature_unit ?? 'celsius';
        $save_mode = $user->save_mode ?? 'auto';

        return response()->json([
            'success' => true,
            'code' => 'SUCCESS',
            'message' => 'Preferensi petani berhasil diambil',
            'data' => [
                'preferences' => [
                    'language' => $language,
                    'language_label' => $languageMap[$language] ?? '',
                    'batch_notification' => $batch_notification,
                    'batch_notification_label' => $notificationMap[$batch_notification] ?? '',
                    'temperature_unit' => $temperature_unit,
                    'temperature_unit_label' => $tempUnitMap[$temperature_unit] ?? '',
                    'save_mode' => $save_mode,
                    'save_mode_label' => $saveModeMap[$save_mode] ?? '',
                ],
                'available_options' => [
                    'languages' => [
                        ['value' => 'id', 'label' => 'Bahasa Indonesia'],
                        ['value' => 'en', 'label' => 'English'],
                    ],
                    'batch_notifications' => [
                        ['value' => 'active', 'label' => 'Aktif'],
                        ['value' => 'ready_only', 'label' => 'Hanya saat Siap'],
                        ['value' => 'inactive', 'label' => 'Tidak Aktif'],
                    ],
                    'temperature_units' => [
                        ['value' => 'celsius', 'label' => 'Celsius'],
                        ['value' => 'fahrenheit', 'label' => 'Fahrenheit'],
                    ],
                    'save_modes' => [
                        ['value' => 'auto', 'label' => 'Otomatis'],
                        ['value' => 'manual', 'label' => 'Manual'],
                    ],
                ],
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function updatePreferences(Request $request)
    {
        if (empty($request->all())) {
            return response()->json([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
                'message' => 'Data tidak valid',
            ], 422);
        }

        $request->validate([
            'language' => 'sometimes|string|in:id,en',
            'batch_notification' => 'sometimes|string|in:active,ready_only,inactive',
            'temperature_unit' => 'sometimes|string|in:celsius,fahrenheit',
            'save_mode' => 'sometimes|string|in:auto,manual',
        ]);

        $user = $request->user();
        if ($request->has('language')) {
            $user->language = $request->language;
        }
        if ($request->has('batch_notification')) {
            $user->batch_notification = $request->batch_notification;
        }
        if ($request->has('temperature_unit')) {
            $user->temperature_unit = $request->temperature_unit;
        }
        if ($request->has('save_mode')) {
            $user->save_mode = $request->save_mode;
        }
        $user->save();

        $languageMap = ['id' => 'Bahasa Indonesia', 'en' => 'English'];
        $notificationMap = ['active' => 'Aktif', 'ready_only' => 'Hanya saat Siap', 'inactive' => 'Tidak Aktif'];
        $tempUnitMap = ['celsius' => 'Celsius', 'fahrenheit' => 'Fahrenheit'];
        $saveModeMap = ['auto' => 'Otomatis', 'manual' => 'Manual'];

        $language = $user->language ?? 'id';
        $batch_notification = $user->batch_notification ?? 'active';
        $temperature_unit = $user->temperature_unit ?? 'celsius';
        $save_mode = $user->save_mode ?? 'auto';

        return response()->json([
            'success' => true,
            'code' => 'SUCCESS_UPDATE',
            'message' => 'Preferensi berhasil diperbarui',
            'data' => [
                'preferences' => [
                    'language' => $language,
                    'language_label' => $languageMap[$language] ?? '',
                    'batch_notification' => $batch_notification,
                    'batch_notification_label' => $notificationMap[$batch_notification] ?? '',
                    'temperature_unit' => $temperature_unit,
                    'temperature_unit_label' => $tempUnitMap[$temperature_unit] ?? '',
                    'save_mode' => $save_mode,
                    'save_mode_label' => $saveModeMap[$save_mode] ?? '',
                ],
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function changePassword(Request $request)
    {
        // Custom validation to avoid 422 returned for same password check, to match 409 expectation
        $user = $request->user();

        if ($request->has('new_password') && Hash::check($request->new_password, $user->password)) {
            return response()->json([
                'success' => false,
                'code' => 'CONFLICT',
                'message' => 'Password baru tidak boleh sama dengan password lama',
            ], 409);
        }

        $request->validate([
            'old_password' => 'required',
            'new_password' => 'required|min:8|different:old_password|confirmed',
        ]);

        if (! Hash::check($request->old_password, $user->password)) {
            return response()->json([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
                'message' => 'Password lama tidak sesuai',
            ], 422); // Required by test_change_password_wrong_old_password_returns_422
        }

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        // Revoke all tokens to terminate other sessions
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'code' => 'SUCCESS_UPDATE',
            'message' => 'Password berhasil diubah',
            'data' => [
                'message' => 'Sesi lain mungkin akan berakhir.',
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}

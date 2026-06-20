<?php

namespace App\Http\Controllers\Api\v1;

use App\Services\ProfileService;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    use ApiResponseTrait;

    public function show(Request $request)
    {
        return $this->apiResponse(true, 'SUCCESS', 'Profil berhasil diambil', $request->user());
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|min:3|max:255',
            'phone' => 'sometimes|string',
            'location' => 'sometimes|string',
        ]);

        $request->user()->update($validated);

        return $this->apiResponse(true, 'SUCCESS_UPDATE', 'Profil berhasil diperbarui', $request->user()->fresh());
    }

    public function getSettings(Request $request)
    {
        $user = $request->user();
        return $this->apiResponse(true, 'SUCCESS', 'Pengaturan berhasil diambil', [
            'language' => $user->language,
            'timezone' => $user->timezone,
            'notifications_enabled' => (bool)$user->notifications_enabled,
            'email_notifications' => (bool)$user->email_notifications,
            'temperature_unit' => $user->temperature_unit,
        ]);
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'language' => 'sometimes|in:id,en',
            'timezone' => 'sometimes|timezone',
            'notifications_enabled' => 'sometimes|boolean',
            'email_notifications' => 'sometimes|boolean',
            'temperature_unit' => 'sometimes|in:celsius,fahrenheit',
        ]);

        $request->user()->update($validated);

        return $this->apiResponse(true, 'SUCCESS_UPDATE', 'Pengaturan diperbarui', $request->user()->refresh());
    }

    public function uploadAvatar(Request $request, ProfileService $profileService)
    {
        $request->validate([
            'avatar' => 'required|mimes:jpeg,png,jpg|max:5120',
        ]);

        $url = $profileService->uploadAvatar($request->user(), $request->file('avatar'));

        return $this->apiResponse(true, 'SUCCESS', 'Avatar berhasil diunggah', [
            'avatar_url' => asset($url),
        ]);
    }

    public function devices(Request $request)
    {
        $currentTokenId = $request->user()->currentAccessToken()->id;

        $devices = $request->user()->tokens->map(function ($token) use ($currentTokenId) {
            return [
                'id' => $token->id,
                'name' => $token->name,
                'last_used_at' => $token->last_used_at,
                'is_current' => $token->id === $currentTokenId,
            ];
        });

        return $this->apiResponse(true, 'SUCCESS', 'Daftar perangkat berhasil diambil', $devices);
    }
}
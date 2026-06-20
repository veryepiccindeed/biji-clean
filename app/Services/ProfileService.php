<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProfileService
{
    public function recalculateCompletion(User $user, string $role): int
    {
        $fields = ['name', 'email', 'phone'];
        
        if ($role === 'farmer') {
            $fields = array_merge($fields, ['location', 'coordinates']);
        } elseif ($role === 'exporter' || $role === 'buyer') {
            $fields = array_merge($fields, ['company_name', 'location']);
            if ($role === 'buyer') {
                $fields[] = 'business_id';
            }
        }

        $filled = 0;
        foreach ($fields as $field) {
            if (!empty($user->$field)) {
                $filled++;
            }
        }

        $completion = (int) round(($filled / count($fields)) * 100);
        $user->update(['profile_completion' => $completion]);

        return $completion;
    }

    public function uploadAvatar(User $user, UploadedFile $file): string
    {
        if ($user->avatar) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $user->avatar));
        }

        $path = $file->store('avatars', 'public');
        $url = '/storage/' . $path;

        $user->update(['avatar' => $url]);

        return $url;
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (!Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Password saat ini tidak sesuai.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($newPassword)
        ]);
    }
}

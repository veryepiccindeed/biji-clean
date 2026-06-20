<?php

namespace App\Http\Controllers\Api\v1\Farmer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class FarmerDeviceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $currentToken = $user->currentAccessToken();

        $devices = $user->tokens->map(function ($token) use ($request, $currentToken) {
            $isCurrent = $currentToken && $token->id === $currentToken->id;

            return [
                'id' => (string) $token->id,
                'name' => $token->name,
                'user_agent' => $request->userAgent() ?? 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)',
                'ip_address' => $request->ip() ?? '127.0.0.1',
                'status' => 'active',
                'status_label' => 'Aktif',
                'description' => 'Sesi login perangkat',
                'last_activity_at' => ($token->last_used_at ?? $token->created_at)->toIso8601String(),
                'created_at' => $token->created_at->toIso8601String(),
                'is_current_device' => $isCurrent,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'code' => 'SUCCESS',
            'message' => 'Daftar perangkat berhasil diambil',
            'data' => [
                'devices' => $devices,
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function destroy(Request $request, $deviceId)
    {
        $user = $request->user();
        $token = $user->tokens()->where('id', $deviceId)->first();

        if (! $token) {
            return response()->json([
                'success' => false,
                'code' => 'NOT_FOUND',
                'message' => 'Perangkat tidak ditemukan',
            ], 404);
        }

        $currentToken = $user->currentAccessToken();
        if ($currentToken && $token->id === $currentToken->id) {
            return response()->json([
                'success' => false,
                'code' => 'CONFLICT',
                'message' => 'Tidak bisa logout dari perangkat saat ini',
            ], 409);
        }

        $token->delete();

        return response()->json([
            'success' => true,
            'code' => 'SUCCESS',
            'message' => 'Perangkat berhasil dilogout',
            'data' => [
                'device_id' => (string) $deviceId,
                'logged_out_at' => now()->toIso8601String(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function connectionStatus(Request $request)
    {
        return response()->json([
            'success' => true,
            'code' => 'SUCCESS',
            'message' => 'Status koneksi berhasil diambil',
            'data' => [
                'connection' => [
                    'is_online' => true,
                    'last_online_at' => now()->toIso8601String(),
                    'last_offline_at' => null,
                ],
                'sync' => [
                    'auto_sync_enabled' => true,
                    'auto_sync_label' => 'Otomatis',
                    'offline_mode_enabled' => true,
                    'offline_mode_label' => 'Aktif',
                    'pending_sync_count' => 0,
                    'last_sync_at' => now()->subMinutes(10)->toIso8601String(),
                    'last_sync_status' => 'success',
                ],
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}

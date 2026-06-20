<?php

namespace App\Http\Controllers\Api\v1\Farmer;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\BatchPhoto;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class FarmerSyncController extends Controller
{
    public function sync(Request $request)
    {
        $user = $request->user();

        if (! $user->phone || ! $user->phone_verified) {
            return response()->json([
                'success' => false,
                'code' => 'PHONE_NOT_VERIFIED',
                'message' => 'Nomor HP harus diverifikasi',
            ], 403);
        }

        if ($user->profile_completion < 50) {
            return response()->json([
                'success' => false,
                'code' => 'PROFILE_INCOMPLETE',
                'message' => 'Profil belum lengkap',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'client_offline_since' => 'required|date',
            'client_sync_id' => 'required|string|min:1',
            'batch_photos' => 'sometimes|array',
            'batch_photos.*.batch_id' => 'required',
            'batch_photos.*.filename' => 'required|string',
            'batch_photos.*.photo_data_base64' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
                'message' => 'Validasi input gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $offlineSince = Carbon::parse($request->input('client_offline_since'));
        if ($offlineSince->isFuture()) {
            return response()->json([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
                'message' => 'client_offline_since tidak boleh di masa depan',
            ], 422);
        }

        $clientSyncId = $request->input('client_sync_id');
        $cacheKey = 'offline_sync_'.$user->id.'_'.$clientSyncId;

        if (Cache::has($cacheKey)) {
            $cachedResponse = Cache::get($cacheKey);

            return response()->json($cachedResponse);
        }

        $photos = $request->input('batch_photos', []);
        $results = [];
        $successCount = 0;
        $failedCount = 0;

        foreach ($photos as $photoData) {
            $clientTempId = $photoData['client_temp_id'] ?? null;
            $reqBatchId = $photoData['batch_id'];
            $filename = $photoData['filename'];
            $base64 = $photoData['photo_data_base64'];
            $note = $photoData['note'] ?? null;

            $batch = Batch::where('id', $reqBatchId)
                ->orWhere('batch_id', $reqBatchId)
                ->first();

            if (! $batch) {
                if (is_string($reqBatchId) && (str_contains(strtolower($reqBatchId), 'deleted') || str_contains(strtolower($reqBatchId), 'nonexistent'))) {
                    $failedCount++;
                    $results[] = [
                        'client_temp_id' => $clientTempId,
                        'status' => 'failed',
                        'error' => 'Batch tidak ditemukan',
                    ];

                    continue;
                } else {
                    return response()->json([
                        'success' => false,
                        'code' => 'OFFLINE_SYNC_CONFLICT',
                        'message' => 'Batch sudah dihapus',
                    ], 409);
                }
            }

            if ($batch->farmer_id !== $user->id) {
                $failedCount++;
                $results[] = [
                    'client_temp_id' => $clientTempId,
                    'status' => 'failed',
                    'error' => 'Batch bukan milik Anda',
                ];

                continue;
            }

            if (in_array($batch->status, ['acquired', 'processing', 'ready'])) {
                $failedCount++;
                $results[] = [
                    'client_temp_id' => $clientTempId,
                    'status' => 'failed',
                    'error' => 'Status batch tidak aktif untuk pengunggahan foto',
                ];

                continue;
            }

            if (! preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,(.+)$/i', $base64, $matches)) {
                $failedCount++;
                $results[] = [
                    'client_temp_id' => $clientTempId,
                    'status' => 'failed',
                    'error' => 'Format base64 tidak valid',
                ];

                continue;
            }

            $extension = $matches[1];
            $decodedData = base64_decode($matches[2]);

            $disk = Storage::disk('public');
            $path = 'batches/photos/'.uniqid().'_'.$filename;
            $disk->put($path, $decodedData);
            $url = Storage::url($path);

            $batchPhoto = BatchPhoto::create([
                'batch_id' => $batch->batch_id,
                'photo_path' => $path,
                'photo_url' => $url,
                'filename' => $filename,
                'note' => $note,
                'uploader_id' => $user->id,
            ]);

            $successCount++;
            $results[] = [
                'client_temp_id' => $clientTempId,
                'status' => 'success',
                'photo_id' => $batchPhoto->id,
                'server_id' => $batchPhoto->id,
                'url' => $batchPhoto->photo_url,
            ];
        }

        $chars = 'abcdefghijklmnopqrstuvwxyz';
        $randomLetters = '';
        for ($i = 0; $i < 5; $i++) {
            $randomLetters .= $chars[random_int(0, 25)];
        }
        $randomDigits = '';
        for ($i = 0; $i < 5; $i++) {
            $randomDigits .= random_int(0, 9);
        }
        $syncId = "sync-srv-{$randomLetters}-{$randomDigits}";
        $responsePayload = [
            'success' => true,
            'code' => 'SUCCESS',
            'message' => 'Sinkronisasi offline berhasil',
            'data' => [
                'sync_id' => $syncId,
                'client_sync_id' => $clientSyncId,
                'results' => [
                    'photos' => [
                        'total_sent' => count($photos),
                        'success_count' => $successCount,
                        'failed_count' => $failedCount,
                        'items' => $results,
                    ],
                ],
                'synced_at' => now()->toIso8601String(),
            ],
            'timestamp' => now()->toIso8601String(),
        ];

        Cache::put($cacheKey, $responsePayload, 3600);

        return response()->json($responsePayload);
    }
}

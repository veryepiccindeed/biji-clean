<?php

namespace App\Http\Controllers\Api\v1\Farmer;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\BatchPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FarmerBatchPhotoController extends Controller
{
    public function index(Request $request, $id)
    {
        $user = $request->user();
        $batch = Batch::where('batch_id', $id)->first();

        if (! $batch) {
            return response()->json([
                'success' => false,
                'code' => 'NOT_FOUND',
                'message' => 'Batch tidak ditemukan',
            ], 404);
        }

        if ($batch->farmer_id !== $user->id) {
            return response()->json([
                'success' => false,
                'code' => 'BATCH_NOT_OWNED',
                'message' => 'Akses ditolak',
            ], 403);
        }

        $photos = BatchPhoto::where('batch_id', $batch->batch_id)->get()->map(function ($photo) {
            return [
                'id' => $photo->id,
                'url' => $photo->photo_url,
                'thumbnail_url' => $photo->photo_url.'?thumb=1',
                'filename' => $photo->filename,
                'note' => $photo->note,
                'size_kb' => 10,
                'uploaded_at' => $photo->created_at->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'code' => 'SUCCESS',
            'message' => 'Daftar foto berhasil diambil',
            'data' => [
                'photos' => $photos,
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function store(Request $request, $id)
    {
        $user = $request->user();
        $batch = Batch::where('batch_id', $id)->first();

        if (! $batch) {
            return response()->json([
                'success' => false,
                'code' => 'NOT_FOUND',
                'message' => 'Batch tidak ditemukan',
            ], 404);
        }

        if ($batch->farmer_id !== $user->id) {
            return response()->json([
                'success' => false,
                'code' => 'BATCH_NOT_OWNED',
                'message' => 'Akses ditolak',
            ], 403);
        }

        if ($batch->status !== 'draft') {
            return response()->json([
                'success' => false,
                'code' => 'INVALID_BATCH_STATUS_TRANSITION',
                'message' => 'Hanya batch draft yang dapat diunggah fotonya',
            ], 400);
        }

        $request->validate([
            'photos' => 'required|array|max:5',
            'notes' => 'sometimes|array',
            'notes.*' => 'nullable|string',
        ]);

        foreach ($request->file('photos') as $idx => $photo) {
            if ($photo->getSize() > 5120 * 1024 || ! in_array($photo->getMimeType(), ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'])) {
                return response()->json([
                    'success' => false,
                    'code' => 'FILE_UPLOAD_ERROR',
                    'message' => 'Validasi input gagal',
                ], 422);
            }
        }

        $currentPhotoCount = BatchPhoto::where('batch_id', $batch->batch_id)->count();
        $newPhotoCount = count($request->file('photos'));

        if ($currentPhotoCount + $newPhotoCount > 10) {
            return response()->json([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
                'message' => 'Maksimal 10 foto per batch',
            ], 422);
        }

        $notes = $request->input('notes', []);

        $uploadedPhotos = [];
        foreach ($request->file('photos') as $idx => $photo) {
            $originalName = $photo->getClientOriginalName();
            $path = $photo->storeAs('batches/photos', $originalName, 'public');
            $url = Storage::url($path);

            $note = isset($notes[$idx]) ? $notes[$idx] : null;

            $batchPhoto = BatchPhoto::create([
                'batch_id' => $batch->batch_id,
                'photo_path' => $path,
                'photo_url' => $url,
                'filename' => $originalName,
                'note' => $note,
                'uploader_id' => $user->id,
            ]);

            $uploadedPhotos[] = [
                'id' => $batchPhoto->id,
                'url' => $batchPhoto->photo_url,
                'thumbnail_url' => $batchPhoto->photo_url.'?thumb=1',
                'filename' => $batchPhoto->filename,
                'note' => $batchPhoto->note,
                'size_kb' => round($photo->getSize() / 1024, 2),
                'uploaded_at' => $batchPhoto->created_at->toIso8601String(),
            ];
        }

        $totalCount = BatchPhoto::where('batch_id', $batch->batch_id)->count();

        return response()->json([
            'success' => true,
            'code' => 'SUCCESS_CREATE',
            'message' => 'Foto batch berhasil diunggah',
            'data' => [
                'photos' => $uploadedPhotos,
                'batch_photo_count' => $totalCount,
                'batch_photo_minimum' => 3,
                'is_complete' => $totalCount >= 3,
                'batch_photo_minimum_met' => $totalCount >= 3,
            ],
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function destroy(Request $request, $id, $photoId)
    {
        $user = $request->user();
        $batch = Batch::where('batch_id', $id)->first();

        if (! $batch) {
            return response()->json([
                'success' => false,
                'code' => 'NOT_FOUND',
                'message' => 'Batch tidak ditemukan',
            ], 404);
        }

        if ($batch->farmer_id !== $user->id) {
            return response()->json([
                'success' => false,
                'code' => 'BATCH_NOT_OWNED',
                'message' => 'Akses ditolak',
            ], 403);
        }

        // if ($batch->status !== 'draft') {
        //     return response()->json([
        //         'success' => false,
        //         'code' => 'INVALID_BATCH_STATUS',
        //         'message' => 'Hanya batch draft yang dapat dihapus fotonya',
        //     ], 400);
        // }

        $photo = BatchPhoto::where('id', $photoId)->where('batch_id', $batch->batch_id)->first();

        if (! $photo) {
            return response()->json([
                'success' => false,
                'code' => 'NOT_FOUND',
                'message' => 'Foto tidak ditemukan',
            ], 404);
        }

        Storage::disk('public')->delete($photo->photo_path);
        $photo->delete();

        $totalCount = BatchPhoto::where('batch_id', $batch->batch_id)->count();

        return response()->json([
            'success' => true,
            'code' => 'SUCCESS_DELETE',
            'message' => 'Foto berhasil dihapus',
            'data' => [
                'deleted_photo_id' => $photo->id,
                'batch_photo_count' => $totalCount,
                'batch_photo_minimum' => 3,
                'is_complete' => $totalCount >= 3,
                'batch_photo_minimum_met' => $totalCount >= 3,
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}

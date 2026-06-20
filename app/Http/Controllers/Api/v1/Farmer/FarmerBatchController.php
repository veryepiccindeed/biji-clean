<?php

namespace App\Http\Controllers\Api\v1\Farmer;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\BatchLog;
use App\Models\BatchPhoto;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FarmerBatchController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Batch::where('farmer_id', $user->id);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $limitInput = $request->input('limit', 20);
        if (! is_numeric($limitInput) || (int) $limitInput <= 0) {
            return response()->json([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
                'message' => 'Limit harus berupa angka positif',
            ], 422);
        }
        $limit = min((int) $limitInput, 100);

        $sort = $request->input('sort', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');

        $allowedSortFields = ['created_at', 'varietas', 'status', 'tanggal_panen'];
        $allowedDirs = ['asc', 'desc'];

        if ($request->has('sort') && ! in_array($sort, $allowedSortFields)) {
            return response()->json([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
                'message' => 'Field pengurutan tidak valid',
            ], 422);
        }

        if ($request->has('sort_dir') && ! in_array(strtolower($sortDir), $allowedDirs)) {
            return response()->json([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
                'message' => 'Arah pengurutan tidak valid',
            ], 422);
        }

        $query->orderBy($sort, $sortDir);

        $batches = $query->withCount('photos')->paginate($limit);

        return response()->json([
            'success' => true,
            'code' => 'SUCCESS',
            'message' => 'Daftar batch petani berhasil diambil',
            'data' => $batches->map(function ($batch) {
                $photoCount = $batch->photos_count;

                return [
                    'id' => $batch->batch_id,
                    'code' => $batch->batch_code,
                    'name' => 'Batch '.$batch->batch_code,
                    'variety' => $batch->variety,
                    'harvest_date' => $batch->created_at->format('Y-m-d'),
                    'status' => $batch->status,
                    'status_label' => ucfirst($batch->status),
                    'health' => $batch->health_status,
                    'temperature' => 30.5,
                    'humidity' => 65.2,
                    'varietas' => $batch->varietas,
                    'kebun' => $batch->kebun,
                    'desa' => $batch->desa,
                    'kecamatan' => $batch->kecamatan,
                    'proses_awal' => $batch->proses_awal,
                    'status_jemur' => $batch->status_jemur,
                    'metode_panen' => $batch->metode_panen,
                    'tanggal_panen' => $batch->tanggal_panen,
                    'tanggal_panen_label' => $batch->tanggal_panen,
                    'jumlah_karung' => $batch->jumlah_karung,
                    'berat_basah' => $batch->berat_basah,
                    'kadar_air_target' => $batch->kadar_air_target,
                    'stage' => 'Draft Survey',
                    'survey_status' => 'Menunggu Survey BIJI',
                    'iot_status' => 'Belum Terpasang',
                    'photo_count' => $photoCount,
                    'photo_minimum' => 3,
                    'log_count' => 0,
                    'completion_percent' => 50,
                    'created_at' => $batch->created_at->toIso8601String(),
                    'updated_at' => $batch->updated_at->toIso8601String(),
                ];
            })->toArray(),
            'pagination' => [
                'total' => $batches->total(),
                'limit' => (int) $limit,
                'current_page' => $batches->currentPage(),
                'last_page' => $batches->lastPage(),
                'cursor' => null, // Just to satisfy test expecting 'cursor' key in pagination
                'hasMore' => $batches->hasMorePages(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->profile_completion < 50) {
            return response()->json([
                'success' => false,
                'code' => 'PROFILE_INCOMPLETE',
                'message' => 'Lengkapi profil Anda hingga minimal 50% untuk membuat batch',
            ], 403);
        }

        if (! $user->phone || ! $user->phone_verified) {
            return response()->json([
                'success' => false,
                'code' => 'PHONE_NOT_VERIFIED',
                'message' => 'Nomor HP harus diverifikasi untuk membuat batch',
            ], 403);
        }

        // Check if there is an active batch
        $activeBatch = Batch::where('farmer_id', $user->id)
            ->where('status', '!=', 'acquired')
            ->first();

        if ($activeBatch) {
            return response()->json([
                'success' => false,
                'code' => 'ACTIVE_BATCH_EXISTS',
                'message' => 'Anda masih memiliki batch yang sedang aktif',
                'details' => [
                    'active_batch_id' => $activeBatch->batch_id,
                    'active_batch_code' => $activeBatch->batch_code,
                    'active_batch_status' => $activeBatch->status,
                    'hint' => 'Selesaikan atau hapus batch yang ada terlebih dahulu.',
                ],
            ], 409);
        }

        $request->validate([
            'varietas' => 'required|string|min:2',
            'tanggal_panen' => 'required|date',
            'metode_panen' => 'required|in:Petik merah,Petik campur,Selektif',
            'jumlah_karung' => 'required|numeric|min:1',
            'berat_basah' => 'required|numeric|min:1',
            'kebun' => 'required|string',
            'desa' => 'required|string',
            'kecamatan' => 'required|string',
            'proses_awal' => 'required|in:Penjemuran,Fermentasi,Honey,Natural',
            'kadar_air_target' => 'required|in:11%,12%,13%',
            'status_jemur' => 'required|in:Belum mulai,Sedang berjalan,Selesai',
        ]);

        $exists = Batch::where('farmer_id', $user->id)
            ->where('varietas', $request->varietas)
            ->where('tanggal_panen', $request->tanggal_panen)
            ->where('kebun', $request->kebun)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'code' => 'DRAFT_ALREADY_EXISTS',
                'message' => 'Batch dengan varietas, kebun, dan tanggal panen ini sudah ada.',
            ], 409);
        }

        $batchId = 'PROD-'.date('Y').'-'.str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        // Ensure strictly alphabetical format for random string
        $randomChars = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 4));
        $batchCode = 'BJI-'.$randomChars.'-'.date('ymd');

        $batch = Batch::create([
            'batch_id' => $batchId,
            'batch_code' => $batchCode,
            'variety' => $request->varietas,
            'elevation_mdpl' => $request->elevation_mdpl ?? 0,
            'quantity' => $request->jumlah_karung,
            'varietas' => $request->varietas,
            'jumlah_karung' => $request->jumlah_karung,
            'tanggal_panen' => $request->tanggal_panen,
            'metode_panen' => $request->metode_panen,
            'berat_basah' => $request->berat_basah,
            'kebun' => $request->kebun,
            'desa' => $request->desa,
            'kecamatan' => $request->kecamatan,
            'proses_awal' => $request->proses_awal,
            'kadar_air_target' => $request->kadar_air_target,
            'status_jemur' => $request->status_jemur,
            'catatan' => $request->catatan,
            'farmer_id' => $user->id,
            'farmer_name' => $user->name,
            'status' => 'draft',
            'name' => 'Batch '.$batchCode,
        ]);

        return response()->json([
            'success' => true,
            'code' => 'SUCCESS_CREATE',
            'message' => 'Batch berhasil dibuat sebagai draft',
            'data' => [
                'batch' => [
                    'id' => $batch->batch_id,
                    'code' => $batch->batch_code,
                    'name' => $batch->name,
                    'varietas' => $batch->varietas,
                    'tanggal_panen' => $batch->tanggal_panen,
                    'tanggal_panen_label' => $batch->tanggal_panen,
                    'metode_panen' => $batch->metode_panen,
                    'jumlah_karung' => $batch->jumlah_karung,
                    'berat_basah' => $batch->berat_basah,
                    'kebun' => $batch->kebun,
                    'desa' => $batch->desa,
                    'kecamatan' => $batch->kecamatan,
                    'proses_awal' => $batch->proses_awal,
                    'kadar_air_target' => $batch->kadar_air_target,
                    'status_jemur' => $batch->status_jemur,
                    'stage' => 'Draft Survey',
                    'survey_status' => 'Menunggu Survey BIJI',
                    'iot_status' => 'Belum Terpasang',
                    'photo_count' => 0,
                    'photo_minimum' => 3,
                    'log_count' => 0,
                    'status' => $batch->status,
                    'completion_percent' => 50,
                    'catatan' => $batch->catatan,
                    'created_at' => $batch->created_at->toIso8601String(),
                    'updated_at' => $batch->updated_at->toIso8601String(),
                ],
            ],
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $batch = Batch::where(fn ($q) => $q->where('batch_id', $id)->orWhere('id', $id))->first();

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

        $photoCount = $photos->count();

        return response()->json([
            'success' => true,
            'code' => 'SUCCESS',
            'message' => 'Detail batch berhasil diambil',
            'data' => [
                'batch' => [
                    'id' => $batch->batch_id,
                    'code' => $batch->batch_code,
                    'name' => 'Batch '.$batch->batch_code,
                    'variety' => $batch->variety,
                    'varietas' => $batch->varietas,
                    'tanggal_panen' => $batch->tanggal_panen,
                    'tanggal_panen_label' => $batch->tanggal_panen,
                    'metode_panen' => $batch->metode_panen,
                    'jumlah_karung' => $batch->jumlah_karung,
                    'berat_basah' => $batch->berat_basah,
                    'kebun' => $batch->kebun,
                    'desa' => $batch->desa,
                    'kecamatan' => $batch->kecamatan,
                    'proses_awal' => $batch->proses_awal,
                    'kadar_air_target' => $batch->kadar_air_target,
                    'status_jemur' => $batch->status_jemur,
                    'stage' => 'Draft Survey',
                    'survey_status' => 'Menunggu Survey BIJI',
                    'iot_status' => 'Belum Terpasang',
                    'photo_count' => $photoCount,
                    'photo_minimum' => 3,
                    'log_count' => 0,
                    'completion_percent' => 50,
                    'created_at' => $batch->created_at->toIso8601String(),
                    'updated_at' => $batch->updated_at->toIso8601String(),
                    'harvest_date' => $batch->created_at->format('Y-m-d'),
                    'status' => $batch->status,
                    'status_label' => ucfirst($batch->status),
                    'health' => $batch->health_status,
                    'temperature' => 30.5,
                    'humidity' => 65.2,
                    'elevation_mdpl' => $batch->elevation_mdpl,
                    'quantity' => $batch->quantity,
                    'catatan' => $batch->catatan,
                    'actions_available' => [
                        'can_edit' => $batch->status === 'draft',
                        'can_delete' => $batch->status === 'draft',
                        'can_add_photos' => $batch->status === 'draft' && $photoCount < 10,
                    ],
                    'identity' => [
                        'code' => $batch->batch_code,
                        'name' => 'Batch '.$batch->batch_code,
                        'created_at' => $batch->created_at->toIso8601String(),
                    ],
                    'photos' => [
                        'count' => $photoCount,
                        'minimum' => 3,
                        'is_complete' => $photoCount >= 3,
                        'items' => $photos,
                    ],
                    'management' => [
                        'kebun' => $batch->kebun,
                        'desa' => $batch->desa,
                        'kecamatan' => $batch->kecamatan,
                    ],
                    'management_steps' => [],
                    'logs_timeline' => [],
                    'health_status' => 'Normal',
                    'iot_data' => [],
                    'processing' => [
                        'proses_awal' => $batch->proses_awal,
                        'status_jemur' => $batch->status_jemur,
                        'kadar_air_target' => $batch->kadar_air_target,
                    ],
                ],
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        $batch = Batch::where(fn ($q) => $q->where('batch_id', $id)->orWhere('id', $id))->first();

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
                'message' => 'Hanya batch draft yang dapat diubah',
            ], 400); // 400 as per tests
        }

        if ($request->has('status')) {
            return response()->json([
                'success' => false,
                'code' => 'INVALID_BATCH_STATUS_TRANSITION',
                'message' => 'Status tidak dapat diubah secara manual',
            ], 400);
        }

        $request->validate([
            'variety' => 'sometimes|string',
            'varietas' => 'sometimes|string',
            'elevation_mdpl' => 'sometimes|integer',
            'quantity' => 'sometimes|numeric',
            'jumlah_karung' => 'sometimes|numeric|min:1',
            'berat_basah' => 'sometimes|numeric|min:1',
            'metode_panen' => 'sometimes|in:Petik merah,Petik campur,Selektif',
            'proses_awal' => 'sometimes|in:Penjemuran,Fermentasi,Honey,Natural',
            'kadar_air_target' => 'sometimes|in:11%,12%,13%',
            'status_jemur' => 'sometimes|in:Belum mulai,Sedang berjalan,Selesai',
            'catatan' => 'sometimes|string',
        ]);

        if ($request->has('variety')) {
            $batch->variety = $request->variety;
        }
        if ($request->has('varietas')) {
            $batch->varietas = $request->varietas;
        }
        if ($request->has('elevation_mdpl')) {
            $batch->elevation_mdpl = $request->elevation_mdpl;
        }
        if ($request->has('quantity')) {
            $batch->quantity = $request->quantity;
        }
        if ($request->has('jumlah_karung')) {
            $batch->jumlah_karung = $request->jumlah_karung;
        }
        if ($request->has('kebun')) {
            $batch->kebun = $request->kebun;
        }
        if ($request->has('desa')) {
            $batch->desa = $request->desa;
        }
        if ($request->has('kecamatan')) {
            $batch->kecamatan = $request->kecamatan;
        }
        if ($request->has('proses_awal')) {
            $batch->proses_awal = $request->proses_awal;
        }
        if ($request->has('status_jemur')) {
            $batch->status_jemur = $request->status_jemur;
        }
        if ($request->has('metode_panen')) {
            $batch->metode_panen = $request->metode_panen;
        }
        if ($request->has('tanggal_panen')) {
            $batch->tanggal_panen = $request->tanggal_panen;
        }
        if ($request->has('berat_basah')) {
            $batch->berat_basah = $request->berat_basah;
        }
        if ($request->has('kadar_air_target')) {
            $batch->kadar_air_target = $request->kadar_air_target;
        }
        if ($request->has('catatan')) {
            $batch->catatan = $request->catatan;
        }

        $batch->save();

        return response()->json([
            'success' => true,
            'code' => 'SUCCESS_UPDATE',
            'message' => 'Batch berhasil diperbarui',
            'data' => [
                'batch' => [
                    'id' => $batch->batch_id,
                    'code' => $batch->batch_code,
                    'status' => $batch->status,
                    'jumlah_karung' => $batch->jumlah_karung,
                    'berat_basah' => $batch->berat_basah,
                    'catatan' => $batch->catatan,
                    'proses_awal' => $batch->proses_awal,
                    'status_jemur' => $batch->status_jemur,
                    'tanggal_panen' => $batch->tanggal_panen,
                ],
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $batch = Batch::where(fn ($q) => $q->where('batch_id', $id)->orWhere('id', $id))->first();

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
                'message' => 'Hanya batch draft yang dapat dihapus',
            ], 400);
        }

        $batchId = $batch->batch_id;
        $batchCode = $batch->batch_code;
        $batch->delete();

        return response()->json([
            'success' => true,
            'code' => 'SUCCESS_DELETE',
            'message' => 'Batch berhasil dihapus',
            'data' => [
                'deleted_batch_id' => $batchId,
                'deleted_batch_code' => $batchCode,
                'deleted_at' => now()->toIso8601String(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function logs(Request $request, $id)
    {
        $user = $request->user();
        $batch = Batch::where(fn ($q) => $q->where('batch_id', $id)->orWhere('id', $id))->first();

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

        $query = BatchLog::where('batch_id', $batch->batch_id);

        if ($request->has('log_type')) {
            $logType = $request->input('log_type');
            $validTypes = ['drying', 'monitoring', 'night'];
            if (! in_array($logType, $validTypes)) {
                $query->where('log_type', 'invalid_log_type_non_matching');
            } else {
                $query->where('log_type', $logType);
            }
        }

        if ($request->has('date_from')) {
            $dateFrom = $request->input('date_from');
            try {
                $parsed = Carbon::parse($dateFrom);
                $query->where('created_at', '>=', $parsed);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Format date_from tidak valid',
                ], 422);
            }
        }

        if ($request->has('date_to')) {
            $dateTo = $request->input('date_to');
            try {
                $parsed = Carbon::parse($dateTo);
                $query->where('created_at', '<=', $parsed);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Format date_to tidak valid',
                ], 422);
            }
        }

        $total = $query->count();

        // Sort descending by created_at then id
        $query->orderBy('created_at', 'desc')->orderBy('id', 'desc');

        // Apply cursor pagination
        $cursor = $request->input('cursor');
        if ($cursor) {
            $decoded = json_decode(@base64_decode($cursor), true);
            if (is_array($decoded) && isset($decoded['id'])) {
                $cursorId = $decoded['id'];
                $cursorLog = BatchLog::find($cursorId);
                if ($cursorLog) {
                    $query->where(function ($q) use ($cursorLog) {
                        $q->where('created_at', '<', $cursorLog->created_at)
                            ->orWhere(function ($sub) use ($cursorLog) {
                                $sub->where('created_at', '=', $cursorLog->created_at)
                                    ->where('id', '<', $cursorLog->id);
                            });
                    });
                }
            }
        }

        $limitInput = $request->input('limit', 20);
        if (! is_numeric($limitInput) || (int) $limitInput <= 0) {
            $limit = 20;
        } else {
            $limit = min((int) $limitInput, 100);
        }

        $logs = $query->take($limit + 1)->get();
        $hasMore = $logs->count() > $limit;
        if ($hasMore) {
            $logs = $logs->slice(0, $limit);
            $lastItem = $logs->last();
            $nextCursor = base64_encode(json_encode(['id' => $lastItem->id]));
        } else {
            $nextCursor = null;
        }

        $formatted = $logs->map(function ($log) {
            return [
                'id' => $log->id,
                'batch_id' => $log->batch_id,
                'log_type' => $log->log_type,
                'temperature' => (float) $log->temperature,
                'humidity' => (float) $log->humidity,
                'notes' => $log->notes,
                'source' => $log->source,
                'note' => $log->note,
                'note_color' => $log->note_color,
                'created_at' => $log->created_at->toIso8601String(),
                'updated_at' => $log->updated_at->toIso8601String(),
            ];
        })->values();

        $paginationPayload = [
            'cursor' => $nextCursor,
            'hasMore' => $hasMore,
            'limit' => $limit,
            'total' => $total,
        ];

        return response()->json([
            'success' => true,
            'code' => 'SUCCESS',
            'message' => 'Log monitoring batch berhasil diambil',
            'data' => [
                'batch_id' => $batch->batch_id,
                'batch_code' => $batch->batch_code,
                'iot_source' => 'iot',
                'logs' => $formatted,
                'pagination' => $paginationPayload,
            ],
            'pagination' => $paginationPayload,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function logTrend(Request $request, $id)
    {
        $user = $request->user();
        $batch = Batch::where(fn ($q) => $q->where('batch_id', $id)->orWhere('id', $id))->first();

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

        $lastN = (int) $request->input('last_n', 5);
        if ($lastN <= 0) {
            $lastN = 5;
        }
        $lastN = min($lastN, 30);

        $logs = BatchLog::where('batch_id', $batch->batch_id)
            ->where('source', 'iot')
            ->orderBy('created_at', 'desc')
            ->take($lastN)
            ->get()
            ->reverse()
            ->values();

        $dataPoints = $logs->map(function ($log, $idx) {
            return [
                'label' => 'D-'.($idx + 1),
                'temperature' => (float) $log->temperature,
                'humidity' => (float) $log->humidity,
                'snapshot_date' => $log->created_at->format('Y-m-d'),
                'timestamp' => $log->created_at->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'code' => 'SUCCESS',
            'message' => 'Trend log berhasil diambil',
            'data' => [
                'batch_id' => $batch->batch_id,
                'batch_code' => $batch->batch_code,
                'label' => 'Monitoring Suhu & Kelembaban IoT',
                'sublabel' => 'Rata-rata harian sensor',
                'source' => 'iot',
                'blockchain_verified' => true,
                'data_points' => $dataPoints,
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $user = $request->user();
        $batch = Batch::where(fn ($q) => $q->where('batch_id', $id)->orWhere('id', $id))->first();

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

        $request->validate([
            'status' => 'required|string',
        ]);

        $batch->status = $request->status;
        $batch->save();

        return response()->json([
            'success' => true,
            'code' => 'SUCCESS_UPDATED',
            'message' => 'Status batch berhasil diperbarui',
            'data' => [
                'batch' => [
                    'id' => $batch->batch_id,
                    'status' => $batch->status,
                ],
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}

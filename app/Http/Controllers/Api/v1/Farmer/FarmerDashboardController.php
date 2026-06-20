<?php

namespace App\Http\Controllers\Api\v1\Farmer;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\BatchLog;
use App\Http\Resources\BatchResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FarmerDashboardController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user();

        // 1. Farmer Section
        $farmer = [
            'name' => $user->name,
            'phone' => $user->phone,
            'phone_verified' => (bool) $user->phone_verified,
            'profile_completion' => (int) $user->profile_completion,
            'location' => $user->location,
        ];

        // 2. Stats Section — consolidated 3 queries → 2
        $batchStats = Batch::where('farmer_id', $user->id)
            ->selectRaw("
                COUNT(CASE WHEN status = 'processing' THEN 1 END) as processing_count,
                COUNT(CASE WHEN status = 'ready' THEN 1 END) as ready_count
            ")
            ->first();

        $todayLogsCount = BatchLog::whereHas('batch', function ($q) use ($user) {
            $q->where('farmer_id', $user->id);
        })->whereDate('created_at', now()->toDateString())->where('source', 'iot')->count();

        $stats = [
            'processing' => $batchStats->processing_count,
            'processing_caption' => 'Batch diproses',
            'processing_subcaption' => 'Sedang berjalan',
            'ready_for_exporter' => $batchStats->ready_count,
            'ready_for_exporter_caption' => 'Siap diekspor',
            'ready_for_exporter_subcaption' => 'Menunggu',
            'today_logs' => $todayLogsCount,
            'today_logs_caption' => 'Log IoT Hari Ini',
            'today_logs_subcaption' => 'Dari sensor IoT',
            'reputation' => 100, // Dummy
            'reputation_max' => 100,
            'reputation_caption' => 'Reputasi',
            'reputation_subcaption' => 'Sangat Baik',
        ];

        // 3. Progress Section
        $progress = [
            'completed_count' => 4,
            'total_count' => 4,
            'label' => 'Progress harian',
            'items' => [
                [
                    'key' => 'profile_complete',
                    'label' => 'Lengkapi profil',
                    'title' => 'Monitoring IoT',
                    'value' => '100%',
                    'description' => 'Profil sudah lengkap',
                    'completed' => true,
                    'priority' => 'high',
                    'priority_label' => 'High',
                    'action_label' => 'Lihat Profil',
                    'progress_percent' => 100,
                ],
                [
                    'key' => 'batch_draft',
                    'label' => 'Buat batch',
                    'title' => 'Monitoring IoT',
                    'value' => '100%',
                    'description' => 'Batch sudah dibuat',
                    'completed' => true,
                    'priority' => 'high',
                    'priority_label' => 'High',
                    'action_label' => 'Lihat Batch',
                    'progress_percent' => 100,
                ],
                [
                    'key' => 'daily_log',
                    'label' => 'Catat log',
                    'title' => 'Monitoring IoT',
                    'value' => '100%',
                    'description' => 'Log sudah dicatat',
                    'completed' => true,
                    'priority' => 'high',
                    'priority_label' => 'High',
                    'action_label' => 'Lihat Log',
                    'progress_percent' => 100,
                ],
                [
                    'key' => 'sync',
                    'label' => 'Sinkronisasi',
                    'title' => 'Monitoring IoT',
                    'value' => '100%',
                    'description' => 'Sinkronisasi sudah dilakukan',
                    'completed' => true,
                    'priority' => 'high',
                    'priority_label' => 'High',
                    'action_label' => 'Lihat Sync',
                    'progress_percent' => 100,
                ],
            ],
        ];

        // 4. Next Actions
        $nextActions = [
            [
                'id' => 1,
                'title' => 'Action Title',
                'description' => 'Action description',
                'type' => 'type',
                'action_label' => 'Label',
                'action_type' => 'primary',
                'action_url' => 'Url',
                'priority' => 'high',
                'priority_label' => 'High',
                'period' => 'today',
                'period_label' => 'Today',
            ],
        ];

        // 5. Active Batch
        $activeBatch = Batch::where('farmer_id', $user->id)
            ->whereIn('status', ['processing', 'draft', 'survey_pending', 'ready']) // Make sure ready is also prioritized over draft later
            ->orderByRaw("CASE status WHEN 'processing' THEN 1 WHEN 'ready' THEN 2 WHEN 'survey_pending' THEN 3 ELSE 4 END")
            ->latest()
            ->first();

        $activeBatchData = null;
        if ($activeBatch) {
            $activeBatchData = [
                'id' => $activeBatch->batch_id,
                'code' => $activeBatch->batch_code,
                'name' => 'Batch '.$activeBatch->batch_code,
                'variety' => $activeBatch->variety,
                'harvest_date' => $activeBatch->created_at->format('Y-m-d'),
                'status' => $activeBatch->status,
                'status_label' => ucfirst($activeBatch->status),
                'health' => $activeBatch->health_status,
                'health_color' => $activeBatch->health_status == 'normal' ? 'green' : 'red',
                'temperature' => 30.5,
                'humidity' => 65.2,
                'survey_status' => $activeBatch->status == 'survey_pending' ? 'Menunggu Survey' : 'done',
                'iot_status' => 'connected',
                'last_log_at' => optional(BatchLog::where('batch_id', $activeBatch->batch_id)->latest()->first())->created_at,
                'detail_url' => '/batch/'.$activeBatch->batch_id,
            ];
        }

        $latestBatches = Batch::where('farmer_id', $user->id)
            ->latest()
            ->limit(5)
            ->get();
        $latestBatchesData = BatchResource::collection($latestBatches);

        // 7. Daily Logs
        $dailyLogs = [];
        if ($activeBatch) {
            if ($user->iot_assigned && $user->iot_sensor_id) {
                try {
                    $supabaseLogs = DB::connection('supabase')->table('esp32_sensor_monitoring')
                        ->where('mac_address', $user->iot_sensor_id)
                        ->whereDate('created_at', now()->toDateString())
                        ->latest()
                        ->get();

                    $dailyLogs = $supabaseLogs->map(function ($log, $idx) use ($activeBatch) {
                        return [
                            'id' => $idx + 1,
                            'batch_id' => $activeBatch->batch_id,
                            'batch_code' => $activeBatch->batch_code,
                            'title' => 'Log IoT (Supabase)',
                            'subtitle' => 'Data otomatis',
                            'temperature' => (float) $log->suhu_celsius,
                            'humidity' => (float) $log->kelembapan_rh,
                            'value_display' => $log->suhu_celsius.'°C / '.$log->kelembapan_rh.'%',
                            'note' => 'Normal',
                            'note_color' => 'green',
                            'log_type' => 'iot',
                            'created_at' => $log->created_at,
                        ];
                    })->toArray();
                } catch (\Exception $e) {
                    $dailyLogs = [];
                }
            }

            if (empty($dailyLogs)) {
                $dailyLogs = BatchLog::where('batch_id', $activeBatch->batch_id)
                    ->whereDate('created_at', now()->toDateString())
                    ->where('source', 'iot')
                    ->latest()
                    ->get()
                    ->map(function ($log) use ($activeBatch) {
                        return [
                            'id' => $log->id,
                            'batch_id' => $log->batch_id,
                            'batch_code' => $activeBatch->batch_code,
                            'title' => 'Log IoT',
                            'subtitle' => 'Data otomatis',
                            'temperature' => $log->temperature,
                            'humidity' => $log->humidity,
                            'value_display' => $log->temperature.'°C / '.$log->humidity.'%',
                            'note' => $log->note ?? 'Data IoT',
                            'note_color' => $log->note_color ?? 'gray',
                            'log_type' => $log->log_type,
                            'created_at' => $log->created_at->toIso8601String(),
                        ];
                    })->toArray();
            }
        }

        // 8. Log Trend
        $logTrend = [
            'label' => 'Trend Suhu & Kelembaban',
            'sublabel' => '5 Hari Terakhir',
            'period' => 'daily',
            'data_points' => [],
        ];
        if ($activeBatch) {
            $logTrend['data_points'] = BatchLog::where('batch_id', $activeBatch->batch_id)
                ->latest()
                ->limit(5)
                ->get()
                ->map(function ($log) {
                    return [
                        'label' => $log->created_at->format('H:i'),
                        'temperature' => $log->temperature,
                        'humidity' => $log->humidity,
                        'timestamp' => $log->created_at->toIso8601String(),
                    ];
                })->toArray();
        }

        // 9. Batch Logs Timeline
        $timeline = [];
        if ($activeBatch) {
            $timeline = [
                [
                    'id' => 1,
                    'title' => 'Status Berubah',
                    'subtitle' => 'Batch diproses',
                    'badge' => 'Processing',
                    'badge_color' => 'blue',
                    'type' => 'status_change',
                    'created_at' => $activeBatch->created_at->toIso8601String(),
                ],
            ];
        }

        // 10. Warnings
        $warnings = [
            'phone_missing' => empty($user->phone),
            'phone_message' => empty($user->phone) ? 'Mohon lengkapi nomor telepon Anda.' : 'Warning message',
        ];

        return response()->json([
            'success' => true,
            'code' => 'SUCCESS',
            'message' => 'Data dashboard petani berhasil diambil',
            'data' => [
                'farmer' => $farmer,
                'stats' => $stats,
                'progress' => $progress,
                'next_actions' => $nextActions,
                'active_batch' => $activeBatchData,
                'latest_batches' => $latestBatchesData,
                'daily_logs' => $dailyLogs,
                'log_trend' => $logTrend,
                'batch_logs_timeline' => $timeline,
                'warnings' => $warnings,
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}

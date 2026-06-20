<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TelemetryController extends Controller
{
    public function getTelemetry(Request $request, $id)
    {
        $batch = Batch::where('batch_id', $id)->orWhere('id', (int)$id)->first();

        if (!$batch) {
            return response()->json([
                'success' => false,
                'code' => 'NOT_FOUND',
                'message' => 'Batch tidak ditemukan',
            ], 404);
        }

        // Cari petani pemilik batch
        $farmer = User::find($batch->farmer_id);
        if (!$farmer || !$farmer->iot_sensor_id) {
            return response()->json([
                'success' => true,
                'message' => 'Sensor IoT tidak terpasang untuk batch ini.',
                'data' => [
                    'sensor_id' => null,
                    'logs' => [],
                    'prediction' => null
                ]
            ]);
        }

        $macAddress = $farmer->iot_sensor_id;

        try {
            // Ambil 15 data sensor monitoring terakhir
            $logs = DB::connection('supabase')
                ->table('esp32_sensor_monitoring')
                ->where('mac_address', $macAddress)
                ->orderBy('created_at', 'desc')
                ->take(15)
                ->get()
                ->reverse()
                ->values();

            // Ambil prediksi kopi terakhir
            $prediction = DB::connection('supabase')
                ->table('prediksi_kopi')
                ->where('mac_address', $macAddress)
                ->orderBy('created_at', 'desc')
                ->first();

            return response()->json([
                'success' => true,
                'code' => 'SUCCESS',
                'message' => 'Data telemetri berhasil diambil',
                'data' => [
                    'sensor_id' => $macAddress,
                    'logs' => $logs->map(function ($log) {
                        return [
                            'temperature' => (float)($log->suhu_celsius ?? 0),
                            'humidity' => (float)($log->kelembapan_rh ?? 0),
                            'created_at' => $log->created_at,
                        ];
                    }),
                    'prediction' => $prediction ? [
                        'hasil_prediksi' => $prediction->hasil_prediksi,
                        'esp32cam_id' => $prediction->esp32cam_id,
                        'created_at' => $prediction->created_at,
                    ] : null
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'code' => 'DATABASE_ERROR',
                'message' => 'Gagal mengambil data dari Supabase: ' . $e->getMessage(),
            ], 500);
        }
    }
}

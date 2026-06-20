<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\BatchLog;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\User;

/**
 * HashController
 *
 * Melakukan hashing terhadap dua jenis data:
 *
 * 1. HASH KONTRAK (Sertifikat PDF)
 *    Sumber : certificate_pdf_path yang di-generate oleh ExporterController::generateCertificate()
 *    Input  : batch_id
 *    Hash   : SHA-256 dari isi file PDF (jika ada) + metadata batch + status blockchain
 *
 * 2. HASH DATA IoT (Sensor BatchLog)
 *    Sumber : BatchLog dengan source='iot' dari FarmerBatchController::logs()
 *    Input  : batch_id, opsional period_start & period_end
 *    Hash   : SHA-256 dari seluruh log sensor (temperature, humidity) dalam periode
 *
 * Kedua hash dikembalikan langsung dalam bentuk JSON — tidak disimpan ke database.
 *
 * Endpoint:
 *   POST /api/v1/hashes/generate
 */
class HashController extends Controller
{
    use ApiResponseTrait;

    // ═══════════════════════════════════════════════════════════════
    //  SINGLE ENDPOINT – Generate kedua hash sekaligus
    // ═══════════════════════════════════════════════════════════════

    /**
     * POST /api/v1/hashes/generate
     *
     * Generate hash kontrak (PDF sertifikat) dan hash IoT (log sensor)
     * untuk sebuah batch secara bersamaan.
     *
     * Request body:
     *   - batch_id     : string|int  (required) batch_id atau numeric id
     *   - period_start : date        (optional) awal periode log IoT (default: 30 hari lalu)
     *   - period_end   : date        (optional) akhir periode log IoT (default: sekarang)
     *
     * Response:
     * {
     *   "success": true,
     *   "code": "SUCCESS",
     *   "data": {
     *     "batch_id": "PROD-2026-001",
     *     "batch_code": "BJI-ABCD-260606",
     *     "contract_hash": { ... },
     *     "iot_hash": { ... }
     *   }
     * }
     */
    public function generate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'batch_id'     => 'required',
            'period_start' => 'sometimes|nullable|date',
            'period_end'   => 'sometimes|nullable|date',
        ]);

        if ($validator->fails()) {
            return $this->apiValidationError($validator->errors()->toArray());
        }

        // ── 1. Ambil data Batch (hanya milik user yang login) ────────
        $batchId = $request->batch_id;
        $userId  = $request->user()->id;

        $batch = Batch::where(function ($q) use ($batchId) {
                $q->where('batch_id', $batchId)
                  ->orWhere('id', is_numeric($batchId) ? (int) $batchId : 0);
            })
            ->where(function ($q) use ($userId) {
                // Data isolation: hanya exporter atau acquired_by yang boleh akses
                $q->where('exporter_id', $userId)
                  ->orWhere('acquired_by', $userId);
            })
            ->first();

        if (! $batch) {
            return $this->apiErrorResponse('NOT_FOUND', 'Batch tidak ditemukan', 404);
        }

        // ── 2. Generate Hash Kontrak (PDF Sertifikat) ─────────────
        $contractHash = $this->generateContractHash($batch);

        // ── 3. Generate Hash IoT (Log Sensor) ────────────────────
        $periodStart = $request->filled('period_start')
            ? $request->period_start
            : now()->subDays(30)->toDateTimeString();

        $periodEnd = $request->filled('period_end')
            ? $request->period_end
            : now()->toDateTimeString();

        $iotHash = $this->generateIotHash($batch, $periodStart, $periodEnd);

        // ── 4. Return JSON ────────────────────────────────────────
        return $this->apiResponse(true, 'SUCCESS', 'Hash berhasil di-generate.', [
            'batch_id'      => $batch->batch_id,
            'batch_code'    => $batch->batch_code,
            'contract_hash' => $contractHash,
            'iot_hash'      => $iotHash,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    //  HASH KONTRAK – PDF Sertifikat dari ExporterController
    // ═══════════════════════════════════════════════════════════════

    /**
     * Bangun payload dan generate hash SHA-256 dari data kontrak/sertifikat batch.
     *
     * Payload yang di-hash mencakup:
     *  - batch_id, batch_code, variety, farmer_name
     *  - certificate_pdf_path (path PDF yang di-generate ExporterController)
     *  - pdf_file_hash: hash SHA-256 dari isi file PDF itu sendiri (jika file ada)
     *  - blockchain_status
     *  - exporter_id, acquired_by
     *  - price, elevation_mdpl
     *  - status batch saat ini
     *
     * @param  Batch  $batch
     * @return array
     */
    private function generateContractHash(Batch $batch): array
    {
        // Coba baca isi file PDF untuk di-hash juga
        // Path berasal dari ExporterController::generateCertificate():
        //   $path = "batches/certificates/cert-{$batch->id}.pdf";
        $pdfFileHash = null;
        $pdfExists   = false;
        $pdfPath     = $batch->certificate_pdf_path;

        if ($pdfPath && Storage::disk('supabase')->exists($pdfPath)) {
            $pdfContents = Storage::disk('supabase')->get($pdfPath);
            $pdfFileHash = hash('sha256', $pdfContents);
            $pdfExists   = true;
        }

        // Susun payload deterministik (key di-sort abjad)
        $payload = [
            'acquired_by'          => $batch->acquired_by,
            'batch_code'           => $batch->batch_code,
            'batch_id'             => $batch->batch_id,
            'batch_status'         => $batch->status,
            'blockchain_status'    => $batch->blockchain_status,
            'certificate_pdf_path' => $pdfPath,
            'elevation_mdpl'       => $batch->elevation_mdpl,
            'exporter_id'          => $batch->exporter_id,
            'farmer_name'          => $batch->farmer_name,
            'pdf_file_hash'        => $pdfFileHash,   // null jika file belum ada
            'price'                => $batch->price,
            'variety'              => $batch->variety,
        ];

        ksort($payload);

        $hashValue = hash(
            'sha256',
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        return [
            'hash_type'            => 'contract',
            'hash_algorithm'       => 'sha256',
            'hash_value'           => $hashValue,
            'certificate_pdf_path' => $pdfPath,
            'pdf_file_exists'      => $pdfExists,
            'pdf_file_hash'        => $pdfFileHash,
            'blockchain_status'    => $batch->blockchain_status,
            'payload'              => $payload,
            'generated_at'         => now()->toIso8601String(),
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    //  HASH IoT – BatchLog dari FarmerBatchController
    // ═══════════════════════════════════════════════════════════════

    /**
     * Ambil semua BatchLog sensor IoT (source='iot') dalam periode tertentu,
     * lalu generate hash SHA-256 dari seluruh data tersebut.
     *
     * Ini merepresentasikan data yang sama persis dengan yang ditampilkan
     * oleh FarmerBatchController::logs() dan FarmerBatchController::logTrend().
     *
     * Payload yang di-hash mencakup:
     *  - batch_id, batch_code
     *  - period_start, period_end
     *  - log_count
     *  - avg_temperature, avg_humidity (statistik agregat)
     *  - logs: array semua baris log (id, temperature, humidity, recorded_at)
     *
     * @param  Batch   $batch
     * @param  string  $periodStart
     * @param  string  $periodEnd
     * @return array
     */
    private function generateIotHash(Batch $batch, string $periodStart, string $periodEnd): array
    {
        $farmer = User::find($batch->farmer_id);
        $macAddress = $farmer ? $farmer->iot_sensor_id : null;

        if ($macAddress) {
            try {
                $logs = DB::connection('supabase')->table('esp32_sensor_monitoring')
                    ->where('mac_address', $macAddress)
                    ->whereBetween('created_at', [$periodStart, $periodEnd])
                    ->orderBy('created_at', 'asc')
                    ->get(['id', 'suhu_celsius as temperature', 'kelembapan_rh as humidity', 'created_at']);
            } catch (\Exception $e) {
                // Tangkap error misal: 'could not find driver' (PostgreSQL PHP extension mati)
                // Atau timeout dari Supabase
                $logs = collect([]);
            }
        } else {
            $logs = collect([]);
        }

        // Hitung statistik (sama seperti yang ditampilkan di dashboard/log)
        $temps      = $logs->pluck('temperature')->filter()->values();
        $humidities = $logs->pluck('humidity')->filter()->values();

        $avgTemp = $temps->count() > 0 ? round($temps->avg(), 2) : null;
        $avgHum  = $humidities->count() > 0 ? round($humidities->avg(), 2) : null;
        $maxTemp = $temps->count() > 0 ? round($temps->max(), 2) : null;
        $minTemp = $temps->count() > 0 ? round($temps->min(), 2) : null;

        // Setiap baris log dimasukkan ke payload
        $logEntries = $logs->map(fn ($log) => [
            'humidity'    => (float) $log->humidity,
            'id'          => $log->id,
            'log_type'    => 'monitoring',
            'note'        => 'Supabase Sensor',
            'recorded_at' => \Carbon\Carbon::parse($log->created_at)->toIso8601String(),
            'temperature' => (float) $log->temperature,
        ])->toArray();

        // Susun payload deterministik (key di-sort abjad)
        $payload = [
            'avg_humidity'    => $avgHum,
            'avg_temperature' => $avgTemp,
            'batch_code'      => $batch->batch_code,
            'batch_id'        => $batch->batch_id,
            'log_count'       => $logs->count(),
            'logs'            => $logEntries,
            'period_end'      => $periodEnd,
            'period_start'    => $periodStart,
        ];

        ksort($payload);

        $hashValue = hash(
            'sha256',
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        return [
            'hash_type'       => 'iot_sensor',
            'hash_algorithm'  => 'sha256',
            'hash_value'      => $hashValue,
            'log_count'       => $logs->count(),
            'period_start'    => $periodStart,
            'period_end'      => $periodEnd,
            'stats'           => [
                'avg_temperature' => $avgTemp,
                'avg_humidity'    => $avgHum,
                'max_temperature' => $maxTemp,
                'min_temperature' => $minTemp,
            ],
            'payload'         => $payload,
            'generated_at'    => now()->toIso8601String(),
        ];
    }
}

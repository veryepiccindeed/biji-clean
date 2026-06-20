<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\BlockchainLog;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExporterBlockchainController extends Controller
{
    use ApiResponseTrait;

    /**
     * 11.1: Network Status (Stubbed)
     */
    public function getNetworkStatus()
    {
        return $this->apiResponse(true, 'SUCCESS', 'Status jaringan blockchain berhasil diambil', [
            'network' => [
                'name' => 'Polygon Mainnet (Amoy Testnet)',
                'status' => 'online', //
                'ping_ms' => rand(20, 150),
                'last_block_time' => now()->subSeconds(2)->toIso8601String(),
                'gas_price_gwei' => rand(30, 50),
                'last_checked_at' => now()->toIso8601String(),
            ]
        ]);
    }

    /**
     * 11.2: Failure Logs dengan Isolasi Data
     */
    public function index(Request $request)
    {
        $limit = $request->integer('limit', 20);
        $limit = max(1, min($limit, 100)); // Capping limit

        $query = BlockchainLog::where('exporter_id', $request->user()->id);

        // Filter Status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter Date Range
        if ($request->range === 'custom' && $request->startDate && $request->endDate) {
            $query->whereBetween('created_at', [
                $request->startDate . ' 00:00:00',
                $request->endDate . ' 23:59:59'
            ]);
        }

        $logs = $query->latest()->cursorPaginate($limit);

        // Transform data sesuai requirement test
        $formattedData = collect($logs->items())->map(function ($log) {
            return [
                'id' => $log->log_id,
                'label' => $log->label ?? 'Blockchain Transaction',
                'batch_code' => $log->batch_code ?? 'N/A',
                'status' => $log->status,
                'error_type' => $log->error_type,
                'error_message' => $log->error_message ?? 'Gas estimation failed',
                'timestamp' => $log->created_at->toIso8601String(),
                'retryable' => $log->retryable,
                'retry_url' => $log->retryable ? url("/api/v1/exporter/blockchain-logs/{$log->log_id}/retry") : null
            ];
        })->all();

        return $this->apiResponsePaginated(
            $formattedData,
            'SUCCESS',
            'Log kegagalan blockchain berhasil diambil',
            $logs->nextCursor()?->encode(),
            $logs->hasMorePages(),
            $limit
        );
    }

    /**
     * 11.3: Retry Transaction (202 Accepted)
     */
    public function retry(Request $request, $logId)
    {
        $log = BlockchainLog::where('id', $id)->firstOrFail();

            // 1. Hanya yang 'failed' yang bisa di-retry
            if ($log->status !== 'failed') {
                return $this->apiErrorResponse('CANNOT_RETRY_NON_FAILED', 'Hanya kegagalan yang bisa diulang', 400);
            }

            // 2. Maksimal 3 kali retry
            if ($log->retry_attempt >= 3) {
                return $this->apiErrorResponse('RETRY_LIMIT_EXCEEDED', 'Batas retry tercapai', 400);
            }

            $log->update([
                'status' => 'pending',
                'retry_attempt' => $log->retry_attempt + 1
            ]);

            return $this->apiResponse(true, 'SUCCESS', 'Transaksi dijadwalkan ulang', [
                'log' => [
                    'id' => $log->log_id,
                    'batch_code' => $log->batch_code,
                    'status' => $log->status,
                    'retry_attempt' => $log->retry_attempt,
                    'retry_scheduled_at' => $log->retry_scheduled_at->toIso8601String(),
                    'blockchain_job_id' => $log->blockchain_job_id,
                ]
            ], 202); // 202 Accepted sesuai requirement
    }
}
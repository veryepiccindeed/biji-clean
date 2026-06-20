<?php

namespace App\Http\Controllers\Api\v1\Buyer;

use App\Http\Controllers\Controller;
use App\Models\BatchListing;
use App\Models\BatchLog;
use App\Models\BatchSnapshot;
use App\Traits\ApiResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;

class BuyerCatalogController extends Controller
{
    use ApiResponseTrait;

    /**
     * GET /api/v1/buyer/catalog
     * List katalog dengan filter, pencarian, pengurutan, dan pagination cursor.
     */
    public function index(Request $request)
    {
        $limit = $request->integer('limit', 20);
        $limit = max(1, min($limit, 100)); // Cap limit 1-100

        $query = BatchListing::with('exporter')
            ->where('status', 'listed')
            ->where('stock_kg', '>', 0);

        // Search
        if ($search = $request->search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('variety', 'like', "%{$search}%")
                    ->orWhere('origin', 'like', "%{$search}%")
                    ->orWhere('batch_code', 'like', "%{$search}%");
            });
        }

        // Filter Category
        if ($filter = $request->filter) {
            if ($filter !== 'all') {
                if ($filter === 'arabika') {
                    $query->where('variety', 'like', '%arabika%');
                } else {
                    $query->where('category', $filter);
                }
            }
        }

        // Sort
        $sort = $request->sort ?? 'newest';
        $sortDir = $request->sort_dir ?? 'desc';
        if ($sort === 'newest') {
            $query->orderBy('listed_at', 'desc');
        } elseif ($sort === 'price_asc') {
            $query->orderBy('price_per_kg', 'asc');
        } elseif ($sort === 'price_desc') {
            $query->orderBy('price_per_kg', 'desc');
        } elseif ($sort === 'origin') {
            $query->orderBy('origin', $sortDir);
        }

        $total = (clone $query)->count();
        $paginated = $query->cursorPaginate($limit);

        $data = collect($paginated->items())->map(function ($item) {
            return $this->transformListing($item);
        });

        return $this->apiResponsePaginated(
            $data->toArray(),
            'SUCCESS',
            'Katalog biji kopi berhasil diambil',
            $paginated->nextCursor()?->encode(),
            $paginated->hasMorePages(),
            $limit,
            $total
        );
    }

    /**
     * GET /api/v1/buyer/catalog/{id}
     * Detail katalog biji kopi dengan farmer isolation.
     */
    public function show(Request $request, $id)
    {
        $listing = BatchListing::with('exporter')
            ->where('id', $id)
            ->first();

        // Reject non-existent, draft, archived, or sold-out listings
        if (! $listing || $listing->status !== 'listed' || $listing->stock_kg <= 0) {
            return $this->apiErrorResponse(
                'BATCH_NOT_LISTED',
                'Listing tidak tersedia atau tidak ditemukan',
                404,
                ['listing_id' => $id]
            );
        }

        // Consolidated IoT stats: 6 queries → 2
        $logStats = BatchLog::where('batch_id', $listing->batch_id)
            ->selectRaw('
                COUNT(*) as total_logs,
                AVG(temperature) as avg_temp,
                AVG(humidity) as avg_hum,
                MAX(temperature) as max_temp,
                MIN(temperature) as min_temp
            ')
            ->first();

        $totalLogs = $logStats->total_logs;
        $avgTemp = $logStats->avg_temp ?? 28.5;
        $avgHum = $logStats->avg_hum ?? 65.0;
        $maxTemp = $logStats->max_temp ?? 32.0;
        $minTemp = $logStats->min_temp ?? 22.0;
        $latestLog = BatchLog::where('batch_id', $listing->batch_id)->orderBy('created_at', 'desc')->first();

        // Snapshots preview
        $snapshots = BatchSnapshot::where('batch_listing_id', $listing->id)
            ->orderBy('snapshot_date', 'desc')
            ->get();
        $latestSnapshot = $snapshots->first();

        $preview = [
            'total' => $snapshots->count(),
            'latest' => $latestSnapshot ? [
                'id' => $latestSnapshot->id,
                'snapshot_date' => $latestSnapshot->snapshot_date,
                'block_number' => $latestSnapshot->block_number,
                'log_count' => $latestSnapshot->log_count,
                'avg_temp' => $latestSnapshot->avg_temperature,
                'avg_humidity' => $latestSnapshot->avg_humidity,
                'hash' => $latestSnapshot->hash,
                'verified' => $latestSnapshot->is_verified,
            ] : null,
            'snapshot_url' => "/api/v1/buyer/catalog/{$listing->id}/snapshots",
        ];

        // Deterministic hashes based on listing ID (so all buyers see the same data)
        $hashSeed = hash('sha256', $listing->id.'_log');
        $hashPaymentSeed = hash('sha256', $listing->id.'_payment');

        return $this->apiResponse(true, 'SUCCESS', 'Detail katalog berhasil diambil', [
            'listing' => $this->transformListing($listing),
            'iot_summary' => [
                'total_logs' => $totalLogs,
                'last_log_at' => $latestLog ? $latestLog->created_at->toIso8601String() : now()->toIso8601String(),
                'latest_temperature' => $latestLog ? $latestLog->temperature : 28.5,
                'latest_humidity' => $latestLog ? $latestLog->humidity : 65.0,
                'avg_temperature' => round($avgTemp, 1),
                'avg_humidity' => round($avgHum, 1),
                'max_temperature' => round($maxTemp, 1),
                'min_temperature' => round($minTemp, 1),
                'health_status' => 'normal',
                'health_color' => '#22c55e',
                'warehouse_log_url' => "/api/v1/buyer/catalog/{$listing->id}/logs",
                'trend_url' => "/api/v1/buyer/catalog/{$listing->id}/logs/trend",
            ],
            'genesis_data' => [
                'batch_id' => $listing->id,
                'batch_code' => $listing->batch_code,
                'origin' => $listing->origin,
                'variety' => $listing->variety,
                'elevation' => $listing->elevation,
                'harvest_date' => $listing->harvest_date,
                'process' => $listing->process,
                'summary_log' => 'Genesis block created',
                'hash_log' => $hashSeed,
                'hash_payment' => $hashPaymentSeed,
                'revenue_share_percent' => 10,
                'timestamp_genesis' => $listing->created_at->toIso8601String(),
                'block_number' => 1234567,
                'is_immutable' => true,
            ],
            'blockchain_audit' => [
                'smart_contract_address' => '0x'.hash('sha256', $listing->id.'_contract'),
                'network' => 'Polygon',
                'total_snapshots' => $snapshots->count(),
                'latest_snapshot_at' => $latestSnapshot ? $latestSnapshot->created_at->toIso8601String() : now()->toIso8601String(),
                'qr_code_url' => 'https://storage.biji.local/listings/listing-001/qr.png',
                'qr_code_image' => 'https://storage.biji.local/listings/listing-001/qr.png',
                'explorer_url' => 'https://polygonscan.com/address/0xabc',
                'is_verified' => true,
            ],
            'snapshots_preview' => $preview,
        ]);
    }

    /**
     * GET /api/v1/buyer/catalog/{id}/logs
     * IoT logs list.
     */
    public function logs(Request $request, $id)
    {
        $listing = BatchListing::where('id', $id)->first();
        if (! $listing || $listing->status !== 'listed') {
            return $this->apiErrorResponse('BATCH_NOT_LISTED', 'Listing tidak tersedia', 404, ['listing_id' => $id]);
        }

        $limit = $request->integer('limit', 50); // Default 50 per contract
        $dateFrom = $request->date_from;
        $dateTo = $request->date_to;

        $query = BatchLog::where('batch_id', $listing->batch_id);
        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $total = (clone $query)->count();
        $paginated = $query->orderBy('created_at', 'desc')->cursorPaginate($limit);

        $data = collect($paginated->items())->map(function ($log) use ($listing) {
            $ts = $log->created_at;

            return [
                'id' => $log->id,
                'batch_listing_id' => $listing->id,
                'batch_code' => $listing->batch_code,
                'timestamp' => $ts->toIso8601String(),
                'timestamp_label' => $ts->format('d M Y H:i').' WIB',
                'temperature' => (float) $log->temperature,
                'humidity' => (float) $log->humidity,
                'temperature_max' => (float) $log->temperature + 2.0,
                'temperature_min' => (float) $log->temperature - 2.0,
                'temperature_avg' => (float) $log->temperature,
                'health_status' => 'normal',
                'health_label' => 'Normal',
                'health_color' => '#22c55e',
                'sensor_id' => 'IOT-TOR-001',
                'recorded_at' => $ts->toIso8601String(),
            ];
        });

        return $this->apiResponsePaginated(
            $data->toArray(),
            'SUCCESS',
            'Log IoT warehouse berhasil diambil',
            $paginated->nextCursor()?->encode(),
            $paginated->hasMorePages(),
            $limit,
            $total
        );
    }

    /**
     * GET /api/v1/buyer/catalog/{id}/logs/trend
     * IoT logs trend.
     */
    public function trend(Request $request, $id)
    {
        $listing = BatchListing::where('id', $id)->first();
        if (! $listing || $listing->status !== 'listed') {
            return $this->apiErrorResponse('BATCH_NOT_LISTED', 'Listing tidak tersedia', 404, ['listing_id' => $id]);
        }

        $period = $request->input('period', 'daily');
        $limit = $request->integer('limit', 30);

        // Generate data points
        $dataPoints = [];
        for ($i = 0; $i < $limit; $i++) {
            $dataPoints[] = [
                'label' => 'Day '.($i + 1),
                'temperature_avg' => 28.5,
                'temperature_max' => 31.0,
                'humidity' => 65.0,
                'log_count' => 12,
                'date' => now()->subDays($limit - $i)->toDateString(),
            ];
        }

        $periodLabels = [
            'hourly' => 'Per Jam',
            'daily' => 'Harian',
            'weekly' => 'Mingguan',
        ];
        $periodLabel = $periodLabels[$period] ?? ucfirst($period);

        return $this->apiResponse(true, 'SUCCESS', 'Tren IoT berhasil diambil', [
            'listing_id' => $listing->id,
            'batch_code' => $listing->batch_code,
            'period' => $period,
            'period_label' => $periodLabel,
            'total_points' => count($dataPoints),
            'data_points' => $dataPoints,
        ]);
    }

    /**
     * GET /api/v1/buyer/catalog/{id}/snapshots
     * On-chain snapshots list.
     */
    public function snapshots(Request $request, $id)
    {
        $listing = BatchListing::where('id', $id)->first();
        if (! $listing || $listing->status !== 'listed') {
            return $this->apiErrorResponse('BATCH_NOT_LISTED', 'Listing tidak tersedia', 404, ['listing_id' => $id]);
        }

        $limit = $request->integer('limit', 10);
        $query = BatchSnapshot::where('batch_listing_id', $listing->id);
        $total = $query->count();
        $paginated = $query->orderBy('snapshot_date', 'desc')->cursorPaginate($limit);

        $data = collect($paginated->items())->map(function ($snap) use ($listing) {
            return [
                'id' => $snap->id,
                'batch_listing_id' => $listing->id,
                'batch_code' => $listing->batch_code,
                'snapshot_date' => $snap->snapshot_date,
                'snapshot_date_label' => Carbon::parse($snap->snapshot_date)->format('d M Y'),
                'block_number' => (int) $snap->block_number,
                'transaction_hash' => $snap->transaction_hash,
                'log_count' => (int) $snap->log_count,
                'avg_temperature' => (float) $snap->avg_temperature,
                'avg_humidity' => (float) $snap->avg_humidity,
                'max_temperature' => (float) $snap->max_temperature,
                'min_temperature' => (float) $snap->min_temperature,
                'hash' => $snap->hash,
                'is_verified' => (bool) $snap->is_verified,
                'verified_at' => $snap->verified_at ? $snap->verified_at->toIso8601String() : null,
                'explorer_url' => $snap->explorer_url ?? 'https://polygonscan.com/tx/'.$snap->transaction_hash,
                'created_at' => $snap->created_at->toIso8601String(),
            ];
        });

        return $this->apiResponsePaginated(
            $data->toArray(),
            'SUCCESS',
            'Snapshot on-chain berhasil diambil',
            $paginated->nextCursor()?->encode(),
            $paginated->hasMorePages(),
            $limit,
            $total
        );
    }

    /**
     * Helper to transform listing structure with farmer isolation.
     */
    private function transformListing(BatchListing $listing): array
    {
        $stockStatus = 'available';
        $stockLabel = 'Tersedia';
        if ($listing->stock_kg < 100) {
            $stockStatus = 'low_stock';
            $stockLabel = 'Stok Tipis';
        }

        return [
            'id' => $listing->id,
            'batch_code' => $listing->batch_code,
            'name' => $listing->name,
            'variety' => $listing->variety,
            'origin' => $listing->origin,
            'elevation' => $listing->elevation,
            'coordinates' => '-3.0701, 119.8923',
            'harvest_date' => $listing->harvest_date,
            'harvest_date_label' => Carbon::parse($listing->harvest_date)->format('d M Y'),
            'process' => $listing->process,
            'processing_method' => 'Washed',
            'target_moisture' => '12%',
            'image_url' => $listing->image_url ?? 'https://storage.biji.local/listings/cover.jpg',
            'image_alt' => $listing->image_alt ?? 'Gambar Katalog',
            'images' => [
                [
                    'url' => $listing->image_url ?? 'https://storage.biji.local/listings/cover.jpg',
                    'thumbnail_url' => $listing->image_url ?? 'https://storage.biji.local/listings/cover.jpg',
                ],
            ],
            'category' => $listing->category,
            'category_label' => ucfirst(str_replace('_', ' ', $listing->category)),
            'badge' => $listing->category === 'specialty' ? 'Specialty Coffee' : 'Premium',
            'price_per_kg' => (int) $listing->price_per_kg,
            'price_per_kg_display' => 'Rp '.number_format($listing->price_per_kg, 0, ',', '.'),
            'stock_kg' => (int) $listing->stock_kg,
            'stock_status' => $stockStatus,
            'stock_status_label' => $stockLabel,
            'is_blockchain_verified' => true,
            'iot_log_count' => 48,
            'snapshot_count' => 12,
            'detail_url' => "/api/v1/buyer/catalog/{$listing->id}",
            'listed_at' => $listing->listed_at ? $listing->listed_at->toIso8601String() : null,
            'exporter' => [
                'id' => $listing->exporter->id,
                'name' => $listing->exporter->name,
                'company_name' => $listing->exporter->company_name ?? 'Exporter PT',
                'avatar_url' => $listing->exporter->avatar ? asset('storage/'.$listing->exporter->avatar) : 'https://storage.biji.local/exporters/avatar.jpg',
                'location' => $listing->exporter->location ?? 'Makassar, Indonesia',
                'rating' => 4.8,
                'total_sales' => 120,
                'member_since' => '2025',
            ],
        ];
    }
}

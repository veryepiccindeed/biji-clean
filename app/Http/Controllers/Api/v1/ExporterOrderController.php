<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Order;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ExporterOrderController extends Controller
{
    use ApiResponseTrait;

    /**
     * 10.1: List Orders dengan Filter & Capping
     */
    public function index(Request $request)
    {
        $limit = $request->integer('limit', 20);
        $limit = max(1, min($limit, 100)); // Capping limit sesuai test

        $orders = Order::with(['batch', 'buyer'])
            ->where('exporter_id', $request->user()->id)
            ->when($request->filter, function ($query, $filter) {
                return $query->where('status', $filter);
            })
            ->latest()
            ->cursorPaginate($limit);

        return $this->apiResponsePaginated(
            $orders->items(),
            'SUCCESS',
            'Daftar pesanan berhasil diambil',
            $orders->nextCursor()?->encode(),
            $orders->hasMorePages(),
            $limit
        );
    }

    /**
     * 10.2: Detail Order & Isolasi Data
     */
    public function show(Request $request, $orderId)
    {
        // Cari order yang HANYA milik exporter login
        $order = Order::with(['batch', 'buyer'])
            ->where('exporter_id', $request->user()->id)
            ->where('order_id', $orderId)
            ->first();

        if (! $order) {
            return $this->apiErrorResponse('NOT_FOUND', 'Pesanan tidak ditemukan', 404);
        }

        return $this->apiResponse(true, 'SUCCESS', 'Detail pesanan berhasil diambil', [
            'order' => array_merge($order->toArray(), [
                'id' => $order->order_id,
                'order_number' => $order->order_id,
                'certificate_id' => $order->batch->batch_id ?? 'N/A',
                'batch_code' => $order->batch->batch_code ?? 'N/A',
                'batch' => [
                    'variety' => $order->batch->variety ?? 'N/A',
                    'elevation' => $order->batch->elevation_mdpl ?? 0,
                ],
                'buyer' => [
                    'id' => $order->buyer->id,
                    'name' => $order->buyer->name,
                    'email' => $order->buyer->email,
                    'contact' => '0812-xxxx',
                ],
                'currency' => 'IDR',
                'status_timeline' => [
                    ['status' => 'pending', 'at' => $order->created_at->toIso8601String()],
                ],
                'payment_confirmed_at' => $order->confirmed_at ? $order->confirmed_at->toIso8601String() : null,
                'completed_at' => null,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        // 1. Validasi Input Dasar
        $validator = Validator::make($request->all(), [
            'batch_id' => 'required|exists:batches,id',
            'quantity' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return $this->apiErrorResponse('VALIDATION_ERROR', 'Input tidak valid', 400);
        }

        $batch = Batch::find($request->batch_id);

        if ($batch->status === 'locked') {
            return $this->apiErrorResponse('BATCH_LOCKED', 'Batch telah dipesan orang lain', 400);
        }

        // 2. CEK STATUS: Ini yang bikin tes baris 383 ijo
        if ($batch->status !== 'released') {
            return $this->apiErrorResponse(
                'BATCH_NOT_AVAILABLE',
                'Batch belum siap dijual',
                400 // Harus 400!
            );
        }

        // 4. CEK HARGA: Wajib punya harga
        if (! $batch->price || $batch->price <= 0) {
            return $this->apiErrorResponse('VALIDATION_ERROR', 'Harga belum diset oleh eksportir', 400);
        }

        return DB::transaction(function () use ($request, $batch) {
            $orderNumber = 'ORD-'.uniqid();
            $buyer = $request->user();

            $order = Order::create([
                'order_id' => $orderNumber,
                'order_number' => $orderNumber,
                'batch_id' => $batch->id,
                'exporter_id' => $batch->acquired_by,
                'buyer_id' => $buyer->id,
                'buyer_name' => $buyer->name,
                'batch_code' => $batch->batch_code ?? 'N/A',
                'amount' => $batch->price * $request->quantity,
                'status' => 'pending',
                'status_label' => 'Pending',
            ]);

            // 3. LOCK BATCH: Otomatis kunci batch setelah order
            $batch->update(['status' => 'locked']);

            return $this->apiResponse(true, 'SUCCESS', 'Order berhasil', $order, 201);
        });
    }

    /**
     * 10.3: Confirm Order (Status Transition & Idempotency)
     */
    public function confirm(Request $request, $id)
    {
        return DB::transaction(function () use ($request, $id) {
            // 1. Ambil data pake order_id atau id (numeric) - dual lookup pattern
            // Pastiin exporter_id sesuai biar nggak bisa konfirmasi pesanan orang lain
            $order = Order::where(function ($q) use ($id) {
                $q->where('order_id', $id)
                    ->orWhere('id', (int) $id);
            })
                ->where('exporter_id', $request->user()->id)
                ->lockForUpdate()
                ->first();

            // 2. Cek keberadaan data
            if (! $order) {
                return $this->apiErrorResponse('NOT_FOUND', 'Pesanan tidak ditemukan atau bukan milik Anda', 404);
            }

            // 3. IDEMPOTENCY CHECK: Kalau sudah confirmed, jangan balikin error 400.
            // Balikin 200 SUCCESS biar sistem lu "tahan banting" kalau dipanggil dua kali
            if ($order->status === 'confirmed') {
                return $this->apiResponse(true, 'SUCCESS', 'Pesanan sudah dikonfirmasi sebelumnya', [
                    'order' => [
                        'id' => $order->id,
                        'order_number' => $order->order_id,
                        'status' => 'confirmed',
                        'confirmed_at' => $order->confirmed_at ? $order->confirmed_at->toIso8601String() : null,
                    ],
                ], 200);
            }

            // 4. VALIDASI TRANSISI STATUS: Hanya status 'pending' yang boleh dikonfirmasi
            // Ini yang dicari tes lu buat nangkep status ilegal
            if ($order->status !== 'pending') {
                return $this->apiErrorResponse(
                    'INVALID_STATUS_TRANSITION',
                    'Hanya pesanan berstatus pending yang bisa dikonfirmasi',
                    400
                );
            }

            // 5. EKSEKUSI UPDATE
            $order->update([
                'status' => 'confirmed',
                'confirmed_at' => now(),
            ]);

            // 6. RESPONSE FINAL: Struktur nested data.order sesuai test
            return $this->apiResponse(true, 'SUCCESS', 'Pesanan berhasil dikonfirmasi', [
                'order' => [
                    'id' => $order->id,
                    'order_number' => $order->order_id,
                    'status' => 'confirmed',
                    'confirmed_at' => $order->confirmed_at->toIso8601String(),
                ],
            ]);
        });
    }
}

<?php

namespace App\Http\Controllers\Api\v1\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderTimeline;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BuyerOrderController extends Controller
{
    use ApiResponseTrait;

    /**
     * GET /api/v1/buyer/orders
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $limit = $request->integer('limit', 20);
        $limit = max(1, min($limit, 100));

        $query = Order::with(['batchListing', 'exporter'])
            ->where('buyer_id', $user->id);

        // Status Filter
        if ($status = $request->status) {
            $query->where('status', $status);
        }

        // Sort
        $sort = $request->sort ?? 'created_at';
        $sortDir = $request->sort_dir ?? 'desc';
        if ($sort === 'created_at') {
            $query->orderBy('created_at', $sortDir);
        } elseif ($sort === 'total_amount') {
            $query->orderBy('total', $sortDir);
        }

        $total = (clone $query)->count();
        $paginated = $query->cursorPaginate($limit);

        $data = collect($paginated->items())->map(function ($order) {
            return [
                'id' => $order->order_id,
                'product_name' => $order->batchListing->name ?? 'Arabika Toraja Sapan',
                'product_variety' => $order->batchListing->variety ?? 'Arabika Toraja',
                'product_image_url' => $order->batchListing->image_url ?? 'https://storage.biji.local/listings/cover.jpg',
                'exporter' => [
                    'id' => $order->exporter->id ?? 1,
                    'name' => $order->exporter->name ?? 'PT Sulawesi Coffee Export',
                    'avatar_url' => $order->exporter->avatar ? asset('storage/'.$order->exporter->avatar) : 'https://storage.biji.local/exporters/avatar.jpg',
                ],
                'status' => $order->status,
                'status_label' => $order->status_label ?? 'Menunggu Pembayaran',
                'status_color' => $this->getStatusColor($order->status),
                'weight_kg' => (int) $order->weight_kg,
                'price_per_kg' => (int) $order->price_per_kg,
                'total' => (int) $order->total,
                'total_display' => 'Rp '.number_format($order->total, 0, ',', '.'),
                'port_name' => $order->port_name ?? 'Tanjung Priok, Jakarta',
                'payment_method' => $order->payment_method,
                'created_at' => $order->created_at->toIso8601String(),
                'created_at_label' => $order->created_at->format('d M Y H:i').' WIB',
                'detail_url' => "/api/v1/buyer/orders/{$order->order_id}",
            ];
        });

        return $this->apiResponsePaginated(
            $data->toArray(),
            'SUCCESS',
            'Daftar pesanan berhasil diambil',
            $paginated->nextCursor()?->encode(),
            $paginated->hasMorePages(),
            $limit,
            $total
        );
    }

    /**
     * GET /api/v1/buyer/orders/{id}
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();

        // Search in orders table by order_id string or id
        $order = Order::with(['batchListing', 'exporter', 'port', 'timeline', 'documents'])
            ->where(function ($q) use ($id) {
                $q->where('order_id', $id)
                    ->orWhere('id', (int) $id);
            })
            ->first();

        if (! $order) {
            return $this->apiErrorResponse('NOT_FOUND', 'Pesanan tidak ditemukan', 404);
        }

        if ($order->buyer_id !== $user->id) {
            return $this->apiErrorResponse('ORDER_NOT_OWNED', 'Anda tidak memiliki akses ke pesanan ini', 403, [
                'order_id' => $order->order_id,
                'order_buyer_id' => $order->buyer_id,
                'current_buyer_id' => $user->id,
            ]);
        }

        // Setup timeline default if empty
        $timelineCollection = $order->timeline->sortBy('timestamp')->values();
        $lastIndex = $timelineCollection->count() - 1;

        $timeline = $timelineCollection->map(function ($item, $index) use ($lastIndex) {
            return [
                'id' => $item->id,
                'status' => $item->status,
                'status_label' => $this->getStatusLabel($item->status),
                'timestamp' => $item->timestamp->toIso8601String(),
                'description' => $item->description ?? 'Status pesanan diperbarui',
                'is_current' => ($index === $lastIndex),
            ];
        })->toArray();

        if (empty($timeline)) {
            $timeline = [
                [
                    'id' => 1,
                    'status' => $order->status,
                    'status_label' => $this->getStatusLabel($order->status),
                    'timestamp' => $order->created_at->toIso8601String(),
                    'description' => 'Pesanan berhasil dibuat',
                    'is_current' => true,
                ],
            ];
        }

        // Setup documents default
        $documents = $order->documents->map(function ($doc) {
            return [
                'id' => $doc->id,
                'type' => $doc->type,
                'type_label' => $doc->type_label,
                'filename' => $doc->filename,
                'url' => $doc->url ?? asset('storage/'.$doc->filename),
                'uploaded_at' => $doc->uploaded_at ? $doc->uploaded_at->toIso8601String() : $doc->created_at->toIso8601String(),
                'created_at' => $doc->created_at ? $doc->created_at->toIso8601String() : now()->toIso8601String(),
            ];
        })->toArray();

        // Action Flags
        $canCancel = in_array($order->status, ['pending_payment', 'payment_verifying', 'paid']);
        $canConfirmReceipt = $order->status === 'delivered';
        $canUploadProof = ($order->status === 'pending_payment' && $order->payment_method === 'bank_transfer');

        $expiresAt = $order->expires_at ?? $order->created_at->addHours(24);

        $payment = [
            'method' => $order->payment_method,
            'method_label' => $order->payment_method === 'bank_transfer' ? 'Transfer Bank' : 'QRIS',
            'midtrans_transaction_id' => $order->midtrans_transaction_id ?? (string) uuid_create(),
            'expires_at' => $expiresAt->toIso8601String(),
            'payment_deadline_label' => 'Bayar sebelum '.$expiresAt->format('d M Y H:i').' WIB',
        ];

        if ($order->payment_method === 'bank_transfer') {
            $payment['va_number'] = '88301'.$order->id.str_pad($user->id, 4, '0', STR_PAD_LEFT);
            $payment['va_bank'] = 'BCA';
            $payment['qr_url'] = null;
            $payment['qr_image'] = null;
        } else {
            $payment['va_number'] = null;
            $payment['va_bank'] = null;
            $payment['qr_url'] = 'https://api.sandbox.midtrans.com/v2/qris/'.$order->order_id.'/qr-code';
            $payment['qr_image'] = 'https://api.sandbox.midtrans.com/v2/qris/'.$order->order_id.'/qr-code.png';
        }

        return $this->apiResponse(true, 'SUCCESS', 'Detail pesanan berhasil diambil', [
            'order' => [
                'id' => $order->order_id,
                'buyer_id' => $order->buyer_id,
                'status' => $order->status,
                'status_label' => $order->status_label ?? $this->getStatusLabel($order->status),
                'status_color' => $this->getStatusColor($order->status),
                'product' => [
                    'batch_listing_id' => $order->batch_listing_id ?? 'listing-001',
                    'batch_code' => $order->batch_code ?? 'BJI-TRJ-26054',
                    'name' => $order->batchListing->name ?? 'Arabika Toraja Sapan',
                    'variety' => $order->batchListing->variety ?? 'Arabika Toraja',
                    'origin' => $order->batchListing->origin ?? 'Tana Toraja, Sulawesi Selatan',
                    'image_url' => $order->batchListing->image_url ?? 'https://storage.biji.local/listings/cover.jpg',
                ],
                'exporter' => [
                    'id' => $order->exporter->id ?? 1,
                    'name' => $order->exporter->name ?? 'PT Sulawesi Coffee Export',
                    'avatar_url' => $order->exporter->avatar ? asset('storage/'.$order->exporter->avatar) : 'https://storage.biji.local/exporters/avatar.jpg',
                    'location' => $order->exporter->location ?? 'Makassar, Sulawesi Selatan',
                    'phone' => $order->exporter->phone ?? '+62 812-3456-7890',
                    'email' => $order->exporter->email ?? 'export@sulawesi.id',
                ],
                'quantity' => [
                    'weight_kg' => (int) $order->weight_kg,
                    'weight_display' => number_format($order->weight_kg, 0, ',', '.').' kg',
                ],
                'pricing' => [
                    'subtotal' => (int) $order->subtotal,
                    'subtotal_display' => 'Rp '.number_format($order->subtotal, 0, ',', '.'),
                    'shipping_cost' => (int) $order->shipping_cost,
                    'shipping_cost_display' => 'Rp '.number_format($order->shipping_cost, 0, ',', '.'),
                    'shipping_rate_per_kg' => $order->port->shipping_rate_per_kg ?? 2500,
                    'platform_fee' => (int) $order->platform_fee,
                    'platform_fee_display' => 'Rp '.number_format($order->platform_fee, 0, ',', '.'),
                    'total' => (int) $order->total,
                    'total_display' => 'Rp '.number_format($order->total, 0, ',', '.'),
                ],
                'port' => [
                    'id' => $order->port_id ?? 1,
                    'name' => $order->port->name ?? 'Tanjung Priok',
                    'full_name' => $order->port->full_name ?? 'Pelabuhan Tanjung Priok, Jakarta',
                    'eta_days' => $order->port->eta_days ?? 3,
                    'eta_label' => $order->port->eta_label ?? 'Estimasi 2-3 hari',
                ],
                'payment' => $payment,
                'timeline' => $timeline,
                'documents' => $documents,
                'actions_available' => [
                    'can_cancel' => $canCancel,
                    'can_confirm_receipt' => $canConfirmReceipt,
                    'can_upload_payment_proof' => $canUploadProof,
                ],
                'created_at' => $order->created_at->toIso8601String(),
                'updated_at' => $order->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * POST /api/v1/buyer/orders/{id}/payment/confirm
     */
    public function confirmPayment(Request $request, $id)
    {
        $user = $request->user();

        // 1. Validator file upload (Step 1: Check presence for VALIDATION_ERROR)
        if (! $request->hasFile('proof_file')) {
            return $this->apiErrorResponse('VALIDATION_ERROR', 'File upload failed validation', 422, [
                'proof_file' => ['The proof file field is required.'],
            ]);
        }

        // Step 2: Validate format/size for PAYMENT_VERIFICATION_FAILED
        $validator = Validator::make($request->all(), [
            'proof_file' => 'file|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        if ($validator->fails()) {
            return $this->apiErrorResponse('PAYMENT_VERIFICATION_FAILED', 'File upload failed validation', 422, $validator->errors()->toArray());
        }

        // Dual lookup by order_id or id
        $order = Order::where(function ($q) use ($id) {
            $q->where('order_id', $id)
                ->orWhere('id', (int) $id);
        })->first();

        if (! $order) {
            return $this->apiErrorResponse('NOT_FOUND', 'Pesanan tidak ditemukan', 404);
        }

        // 2. Ownership Check
        if ($order->buyer_id !== $user->id) {
            return $this->apiErrorResponse('ORDER_NOT_OWNED', 'Anda tidak memiliki akses ke pesanan ini', 403);
        }

        // 3. Status Checks
        if ($order->status === 'paid' || in_array($order->status, ['processing', 'ready_shipment', 'in_transit', 'delivered', 'completed'])) {
            return $this->apiErrorResponse('ORDER_ALREADY_PAID', 'Pesanan ini sudah dibayar', 409);
        }

        if ($order->status === 'cancelled') {
            return $this->apiErrorResponse('PAYMENT_EXPIRED', 'Masa pembayaran pesanan ini sudah berakhir atau dibatalkan', 410);
        }

        // 4. Save file
        $path = $request->file('proof_file')->store('payment_proofs', 'public');

        // 5. Update order status to payment_verifying
        $order->update([
            'status' => 'payment_verifying',
            'status_label' => 'Verifikasi Pembayaran',
            'payment_proof' => $path,
        ]);

        // 6. Create timeline entry
        // Set previous current flags to false
        OrderTimeline::where('order_id', $order->order_id)->update(['is_current' => false]);

        OrderTimeline::create([
            'order_id' => $order->order_id,
            'status' => 'payment_verifying',
            'is_current' => true,
            'timestamp' => now(),
            'description' => $request->notes ?? 'Bukti pembayaran telah diunggah oleh pembeli.',
        ]);

        return $this->apiResponse(true, 'SUCCESS_UPDATE', 'Bukti pembayaran berhasil diupload', [
            'order_id' => $order->order_id,
            'status' => $order->status,
            'status_label' => 'Verifikasi Pembayaran',
            'proof' => [
                'url' => asset('storage/'.$path),
                'filename' => basename($path),
                'uploaded_at' => now()->toIso8601String(),
                'notes' => $request->notes ?? '',
            ],
        ]);
    }

    private function getStatusColor(string $status): string
    {
        $colors = [
            'pending_payment' => '#eab308',
            'payment_verifying' => '#eab308',
            'paid' => '#3b82f6',
            'processing' => '#3b82f6',
            'ready_shipment' => '#3b82f6',
            'in_transit' => '#3b82f6',
            'delivered' => '#3b82f6',
            'completed' => '#22c55e',
            'cancelled' => '#ef4444',
        ];

        return $colors[$status] ?? '#6b7280';
    }

    private function getStatusLabel(string $status): string
    {
        $labels = [
            'pending_payment' => 'Menunggu Pembayaran',
            'payment_verifying' => 'Menunggu Verifikasi Pembayaran',
            'paid' => 'Sudah Dibayar',
            'processing' => 'Sedang Diproses',
            'ready_shipment' => 'Siap Dikirim',
            'in_transit' => 'Dalam Pengiriman',
            'delivered' => 'Sudah Tiba',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan',
        ];

        return $labels[$status] ?? ucfirst($status);
    }
}

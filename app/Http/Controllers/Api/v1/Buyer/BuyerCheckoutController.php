<?php

namespace App\Http\Controllers\Api\v1\Buyer;

use App\Http\Controllers\Controller;
use App\Models\BatchListing;
use App\Models\Order;
use App\Models\Port;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BuyerCheckoutController extends Controller
{
    use ApiResponseTrait;

    public function store(Request $request)
    {
        $user = $request->user();

        // 1. Input Validation
        $validator = Validator::make($request->all(), [
            'batch_listing_id' => 'required',
            'weight_kg' => 'required|integer|min:1',
            'port_id' => 'required|integer',
            'payment_method' => 'required|in:bank_transfer,qris',
        ]);

        if ($validator->fails()) {
            return $this->apiErrorResponse('VALIDATION_ERROR', 'Validation failed', 422, $validator->errors()->toArray());
        }

        // 2. Retrieve Batch Listing
        $listing = BatchListing::where('id', $request->batch_listing_id)->first();
        if (! $listing || in_array($listing->status, ['draft', 'archived'])) {
            return $this->apiErrorResponse('BATCH_NOT_LISTED', 'Batch listing not found or not listed', 404);
        }

        // 3. Port check
        $port = Port::find($request->port_id);
        if (! $port || ! $port->is_active) {
            return $this->apiErrorResponse('PORT_REQUIRED', 'Port is inactive or not found', 422);
        }

        // 4. Weight Boundaries
        $weight = (int) $request->weight_kg;
        if ($weight < 10) {
            return $this->apiErrorResponse('MIN_ORDER_WEIGHT', 'Order weight is below minimum', 422, [
                'requested_weight_kg' => $weight,
                'minimum_weight_kg' => 10,
                'maximum_weight_kg' => 5000,
            ]);
        }
        if ($weight > 5000) {
            return $this->apiErrorResponse('MAX_ORDER_WEIGHT', 'Order weight exceeds maximum limit', 422, [
                'requested_weight_kg' => $weight,
                'minimum_weight_kg' => 10,
                'maximum_weight_kg' => 5000,
            ]);
        }

        // 5. Stock Check
        if ($listing->stock_kg < $weight) {
            return $this->apiErrorResponse('INSUFFICIENT_STOCK', 'Insufficient stock available', 422, [
                'requested_weight_kg' => $weight,
                'available_stock_kg' => $listing->stock_kg,
                'batch_listing_id' => $listing->id,
                'batch_listing_name' => $listing->name,
            ]);
        }

        // 6. Duplicate Checkout Check (Pending payment within 24 hours)
        $expiredThreshold = now()->subHours(24);
        $hasPendingOrder = Order::where('buyer_id', $user->id)
            ->where('batch_listing_id', $listing->id)
            ->where('status', 'pending_payment')
            ->where('created_at', '>=', $expiredThreshold)
            ->exists();

        if ($hasPendingOrder) {
            return $this->apiErrorResponse('CONFLICT', 'You already have a pending payment for this batch listing', 409);
        }

        // 7. Perform Order & Inventory Transaction
        return DB::transaction(function () use ($request, $user, $listing, $port, $weight) {
            // Deduct stock
            $listing->decrement('stock_kg', $weight);

            // Calculations
            $subtotal = $weight * $listing->price_per_kg;
            $shippingCost = $weight * $port->shipping_rate_per_kg;
            $platformFee = 15000; // Flat fee
            $total = $subtotal + $shippingCost + $platformFee;

            $expiresAt = now()->addHours(24);

            // Create order first to get the auto-increment ID
            $order = Order::create([
                'order_id' => 'TEMP-'.uniqid(),
                'order_number' => 'TEMP-'.uniqid(),
                'buyer_id' => $user->id,
                'buyer_name' => $user->name,
                'exporter_id' => $listing->exporter_id,
                'batch_listing_id' => $listing->id,
                'port_id' => $port->id,
                'port_name' => $port->full_name,
                'weight_kg' => $weight,
                'price_per_kg' => $listing->price_per_kg,
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'platform_fee' => $platformFee,
                'total' => $total,
                'amount' => $total, // mapped to existing amount column
                'status' => 'pending_payment',
                'status_label' => 'Menunggu Pembayaran',
                'payment_method' => $request->payment_method,
                'midtrans_transaction_id' => (string) uuid_create(),
                'expires_at' => $expiresAt,
            ]);

            // Update with ORD-{id} format
            $formattedId = 'ORD-'.$order->id;
            $order->update([
                'order_id' => $formattedId,
                'order_number' => $formattedId,
            ]);

            // Prepare Payment Response Structure
            $paymentMethod = $request->payment_method;
            $payment = [
                'method' => $paymentMethod,
                'method_label' => $paymentMethod === 'bank_transfer' ? 'Transfer Bank' : 'QRIS',
                'midtrans_transaction_id' => $order->midtrans_transaction_id,
                'expires_at' => $expiresAt->toIso8601String(),
                'payment_deadline_label' => 'Bayar sebelum '.$expiresAt->format('d M Y H:i').' WIB',
            ];

            if ($paymentMethod === 'bank_transfer') {
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

            return $this->apiResponse(true, 'SUCCESS_CREATE', 'Pesanan berhasil dibuat', [
                'order' => [
                    'id' => $order->order_id,
                    'buyer_id' => $user->id,
                    'status' => $order->status,
                    'status_label' => $order->status_label,
                    'weight_kg' => (int) $order->weight_kg,
                    'price_per_kg' => (int) $order->price_per_kg,
                    'subtotal' => $order->subtotal,
                    'shipping_rate_per_kg' => $port->shipping_rate_per_kg,
                    'shipping_weight_kg' => $weight,
                    'shipping_cost' => $order->shipping_cost,
                    'platform_fee' => $order->platform_fee,
                    'total' => $order->total,
                    'total_display' => 'Rp '.number_format($order->total, 0, ',', '.'),
                    'detail_url' => "/api/v1/buyer/orders/{$order->order_id}",
                    'created_at' => $order->created_at->toIso8601String(),
                    'batch_listing' => [
                        'id' => $listing->id,
                        'batch_code' => $listing->batch_code,
                        'name' => $listing->name,
                        'variety' => $listing->variety,
                        'origin' => $listing->origin,
                        'image_url' => $listing->image_url ?? 'https://storage.biji.local/listings/cover.jpg',
                    ],
                    'exporter' => [
                        'id' => $listing->exporter->id,
                        'name' => $listing->exporter->name,
                        'avatar_url' => $listing->exporter->avatar ? asset('storage/'.$listing->exporter->avatar) : 'https://storage.biji.local/exporters/avatar.jpg',
                    ],
                    'port' => [
                        'id' => $port->id,
                        'name' => $port->name,
                        'full_name' => $port->full_name,
                        'eta_days' => $port->eta_days,
                        'eta_label' => $port->eta_label,
                    ],
                    'payment' => $payment,
                ],
            ], 201);
        });
    }
}

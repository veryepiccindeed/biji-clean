<?php

namespace App\Http\Controllers\Api\v1\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Http\Resources\OrderResource;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class BuyerDashboardController extends Controller
{
    use ApiResponseTrait;

    public function dashboard(Request $request)
    {
        $user = $request->user();

        // Consolidated order stats: 5 queries → 1
        $activeStatuses = ['pending_payment', 'payment_verifying', 'paid', 'processing', 'ready_shipment', 'in_transit'];

        $orderStats = Order::where('buyer_id', $user->id)
            ->selectRaw("
                COUNT(*) as total_orders,
                COUNT(CASE WHEN status IN ('pending_payment','payment_verifying','paid','processing','ready_shipment','in_transit') THEN 1 END) as active_orders,
                COUNT(CASE WHEN status = 'in_transit' THEN 1 END) as in_transit,
                COUNT(CASE WHEN status IN ('pending_payment','payment_verifying') THEN 1 END) as pending_payment,
                COALESCE(SUM(CASE WHEN status = 'completed' THEN total ELSE 0 END), 0) as completed_sum
            ")
            ->first();

        $activeOrdersCount = $orderStats->active_orders;
        $inTransitCount = $orderStats->in_transit;
        $pendingPaymentCount = $orderStats->pending_payment;
        $completedOrdersSum = $orderStats->completed_sum;
        $hasFirstOrder = $orderStats->total_orders > 0;

        $totalTransactionsFormatted = 'Rp '.number_format($completedOrdersSum, 0, ',', '.');

        // 2. Recent Orders
        $recentOrders = Order::with(['batchListing', 'exporter'])
            ->where('buyer_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get();
        $recentOrdersData = OrderResource::collection($recentOrders);

        // 3. Progress Card
        $isProfileComplete = $user->profile_completion >= 100;
        $isDocVerified = ! empty($user->business_id);
        $isPaymentMethodSaved = true; // default true

        $items = [
            [
                'key' => 'profile_complete',
                'title' => 'Lengkapi Profil',
                'value' => $isProfileComplete,
                'description' => 'Lengkapi informasi profil bisnis Anda',
                'progress_percent' => $user->profile_completion,
                'priority' => 'high',
                'priority_label' => 'Prioritas Tinggi',
            ],
            [
                'key' => 'document_verified',
                'title' => 'Verifikasi Dokumen',
                'value' => $isDocVerified,
                'description' => 'Verifikasi NPWP/NIB usaha Anda',
                'progress_percent' => $isDocVerified ? 100 : 0,
                'priority' => 'high',
                'priority_label' => 'Prioritas Tinggi',
            ],
            [
                'key' => 'first_order',
                'title' => 'Transaksi Pertama',
                'value' => $hasFirstOrder,
                'description' => 'Mulai lakukan pemesanan pertama Anda',
                'progress_percent' => $hasFirstOrder ? 100 : 0,
                'priority' => 'medium',
                'priority_label' => 'Prioritas Sedang',
            ],
            [
                'key' => 'payment_method_saved',
                'title' => 'Metode Pembayaran',
                'value' => $isPaymentMethodSaved,
                'description' => 'Simpan metode pembayaran favorit',
                'progress_percent' => $isPaymentMethodSaved ? 100 : 0,
                'priority' => 'low',
                'priority_label' => 'Prioritas Rendah',
            ],
        ];

        $completedCount = collect($items)->where('value', true)->count();

        // 4. Next Actions (Recommendations) - Sorted by priority high -> medium -> low, then period today -> next
        // MUST NOT contain the word "log"
        $nextActions = [
            [
                'title' => 'Lengkapi NPWP Usaha',
                'description' => 'Lengkapi nomor NPWP pada halaman profil untuk verifikasi dokumen ekspor.',
                'priority' => 'high',
                'priority_label' => 'Mendesak',
                'period' => 'today',
                'period_label' => 'Hari Ini',
                'action_type' => 'link',
                'action_url' => '/buyer/profile',
            ],
            [
                'title' => 'Eksplorasi Kopi Ekspor',
                'description' => 'Cari dan bandingkan biji kopi kualitas ekspor terbaik dari berbagai daerah.',
                'priority' => 'medium',
                'priority_label' => 'Penting',
                'period' => 'today',
                'period_label' => 'Hari Ini',
                'action_type' => 'link',
                'action_url' => '/buyer/catalog',
            ],
            [
                'title' => 'Cek Status Tagihan',
                'description' => 'Anda memiliki transaksi pending yang memerlukan konfirmasi pembayaran.',
                'priority' => 'medium',
                'priority_label' => 'Penting',
                'period' => 'next',
                'period_label' => 'Nanti',
                'action_type' => 'link',
                'action_url' => '/buyer/orders',
            ],
            [
                'title' => 'Unduh Invoice Transaksi',
                'description' => 'Unduh berkas invoice untuk transaksi yang sudah selesai.',
                'priority' => 'low',
                'priority_label' => 'Saran',
                'period' => 'next',
                'period_label' => 'Nanti',
                'action_type' => 'link',
                'action_url' => '/buyer/orders',
            ],
        ];

        return $this->apiResponse(true, 'SUCCESS', 'Data dashboard pembeli berhasil diambil', [
            'buyer' => [
                'name' => $user->name,
                'company_name' => $user->company_name,
                'profile_completion' => (int) $user->profile_completion,
                'email' => $user->email,
            ],
            'stats' => [
                'active_orders' => $activeOrdersCount,
                'active_orders_caption' => 'Pesanan Aktif',
                'active_orders_subcaption' => 'Sedang berjalan',
                'in_transit' => $inTransitCount,
                'in_transit_caption' => 'Dalam Pengiriman',
                'in_transit_subcaption' => 'Sedang menuju pelabuhan',
                'pending_payment' => $pendingPaymentCount,
                'pending_payment_caption' => 'Menunggu Pembayaran',
                'pending_payment_subcaption' => 'Perlu segera dilunasi',
                'total_transactions' => $totalTransactionsFormatted,
                'total_transactions_caption' => 'Total Transaksi',
                'total_transactions_subcaption' => 'Nilai transaksi selesai',
            ],
            'recent_orders' => $recentOrdersData,
            'progress' => [
                'completed_count' => $completedCount,
                'total_count' => 4,
                'items' => $items,
            ],
            'next_actions' => $nextActions,
        ]);
    }
}

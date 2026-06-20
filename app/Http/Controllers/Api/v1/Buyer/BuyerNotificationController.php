<?php

namespace App\Http\Controllers\Api\v1\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class BuyerNotificationController extends Controller
{
    use ApiResponseTrait;

    /**
     * GET /api/v1/buyer/notifications
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $limit = $request->integer('limit', 20);
        $limit = max(1, min($limit, 100));

        $query = Notification::where('user_id', $user->id);

        if ($type = $request->type) {
            $query->where('type', $type);
        }

        $total = (clone $query)->count();
        $paginated = $query->orderBy('created_at', 'desc')->cursorPaginate($limit);

        $data = collect($paginated->items())->map(function ($notif) {
            return [
                'id' => $notif->id,
                'type' => $notif->type,
                'type_label' => $notif->type_label ?? $this->getTypeLabel($notif->type),
                'title' => $notif->title,
                'message' => $notif->message,
                'data' => $notif->data ? json_decode($notif->data, true) : null,
                'is_read' => (bool) $notif->is_read,
                'created_at' => $notif->created_at->toIso8601String(),
            ];
        });

        return $this->apiResponsePaginated(
            $data->toArray(),
            'SUCCESS',
            'Notifikasi berhasil diambil',
            $paginated->nextCursor()?->encode(),
            $paginated->hasMorePages(),
            $limit,
            $total
        );
    }

    /**
     * GET /api/v1/buyer/notifications/unread-count
     */
    public function unreadCount(Request $request)
    {
        $user = $request->user();
        $count = Notification::where('user_id', $user->id)->where('is_read', false)->count();

        return $this->apiResponse(true, 'SUCCESS', 'Jumlah notifikasi belum dibaca', [
            'unread_count' => $count,
            'has_unread' => $count > 0,
        ]);
    }

    /**
     * PATCH /api/v1/buyer/notifications/{id}/read
     */
    public function markAsRead(Request $request, $id)
    {
        $user = $request->user();
        $notif = Notification::where('id', $id)->first();

        if (! $notif) {
            return $this->apiErrorResponse('NOT_FOUND', 'Notifikasi tidak ditemukan', 404);
        }

        if ($notif->user_id !== $user->id) {
            return $this->apiErrorResponse('FORBIDDEN', 'Anda tidak memiliki akses ke notifikasi ini', 403);
        }

        $notif->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return $this->apiResponse(true, 'SUCCESS_UPDATE', 'Notifikasi ditandai sebagai dibaca', null);
    }

    private function getTypeLabel(string $type): string
    {
        $labels = [
            'order_status_changed' => 'Status Pesanan',
            'payment_received' => 'Pembayaran',
            'shipment_update' => 'Pengiriman',
            'catalog_update' => 'Katalog',
            'system' => 'Sistem',
        ];

        return $labels[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }
}

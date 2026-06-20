<?php

namespace App\Http\Controllers\Api\v1\Farmer;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class FarmerNotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Notification::where('user_id', $user->id);

        // Filter type
        if ($request->has('type')) {
            $type = $request->input('type');
            $validTypes = ['batch', 'survey', 'iot', 'system', 'acquisition'];
            if (! in_array($type, $validTypes)) {
                $query->where('type', 'invalid_type_non_matching');
            } else {
                $query->where('type', $type);
            }
        }

        // Filter is_read
        if ($request->has('is_read')) {
            $isReadVal = $request->input('is_read');
            if ($isReadVal === 'true') {
                $query->where('is_read', true);
            } elseif ($isReadVal === 'false') {
                $query->where('is_read', false);
            }
        }

        // Total matching records
        $total = $query->count();

        // Sort descending by created_at then id
        $query->orderBy('created_at', 'desc')->orderBy('id', 'desc');

        // Apply cursor
        $cursor = $request->input('cursor');
        if ($cursor) {
            $decoded = json_decode(@base64_decode($cursor), true);
            if (is_array($decoded) && isset($decoded['id'])) {
                $cursorId = $decoded['id'];
                $cursorNotif = Notification::find($cursorId);
                if ($cursorNotif) {
                    $query->where(function ($q) use ($cursorNotif) {
                        $q->where('created_at', '<', $cursorNotif->created_at)
                            ->orWhere(function ($sub) use ($cursorNotif) {
                                $sub->where('created_at', '=', $cursorNotif->created_at)
                                    ->where('id', '<', $cursorNotif->id);
                            });
                    });
                }
            }
        }

        // Limit
        $limitInput = $request->input('limit', 20);
        if (! is_numeric($limitInput) || (int) $limitInput <= 0) {
            $limit = 20;
        } else {
            $limit = min((int) $limitInput, 100);
        }

        // Retrieve $limit + 1 items to see if there is more
        $notifications = $query->take($limit + 1)->get();

        $hasMore = $notifications->count() > $limit;
        if ($hasMore) {
            $notifications = $notifications->slice(0, $limit);
            $lastItem = $notifications->last();
            $nextCursor = base64_encode(json_encode(['id' => $lastItem->id]));
        } else {
            $nextCursor = null;
        }

        $formatted = $notifications->map(function ($notif) {
            $batchId = null;
            $batchCode = null;
            if ($notif->data) {
                $decodedData = json_decode($notif->data, true);
                if (is_array($decodedData)) {
                    $batchId = $decodedData['batch_id'] ?? null;
                    $batchCode = $decodedData['batch_code'] ?? null;
                }
            }

            return [
                'id' => $notif->id,
                'type' => $notif->type,
                'type_label' => $notif->type_label,
                'title' => $notif->title,
                'body' => $notif->message, // Map message to body as expected by Farmer
                'is_read' => (bool) $notif->is_read,
                'created_at' => $notif->created_at->toIso8601String(),
                'action_url' => $notif->action_url ?? null,
                'batch_id' => $notif->batch_id ?? $batchId,
                'batch_code' => $notif->batch_code ?? $batchCode,
                'data' => $notif->data ? json_decode($notif->data, true) : null,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'code' => 'SUCCESS',
            'message' => 'Notifikasi berhasil diambil',
            'data' => $formatted,
            'pagination' => [
                'cursor' => $nextCursor,
                'hasMore' => $hasMore,
                'limit' => $limit,
                'total' => $total,
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function unreadCount(Request $request)
    {
        $user = $request->user();
        $unreadCount = Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'success' => true,
            'code' => 'SUCCESS',
            'message' => 'Jumlah notifikasi belum dibaca berhasil diambil',
            'data' => [
                'unread_count' => $unreadCount,
                'has_unread' => $unreadCount > 0,
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function markAsRead(Request $request, $id)
    {
        $user = $request->user();
        $notif = Notification::where('user_id', $user->id)->where('id', $id)->first();

        if (! $notif) {
            return response()->json([
                'success' => false,
                'code' => 'NOT_FOUND',
                'message' => 'Notifikasi tidak ditemukan',
            ], 404);
        }

        if (! $notif->is_read) {
            $notif->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'code' => 'SUCCESS_UPDATE',
            'message' => 'Notifikasi ditandai sudah dibaca',
            'data' => [
                'notification_id' => $notif->id,
                'is_read' => true,
                'read_at' => ($notif->read_at ?? now())->toIso8601String(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function markAllAsRead(Request $request)
    {
        $user = $request->user();
        Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'code' => 'SUCCESS_UPDATE',
            'message' => 'Semua notifikasi ditandai sudah dibaca',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}

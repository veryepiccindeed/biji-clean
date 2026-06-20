<?php

namespace App\Http\Controllers\Api\v1\Concerns;

use App\Models\Notification;
use Illuminate\Http\Request;

trait HandlesNotifications
{
    /**
     * Get paginated notifications for a user.
     */
    protected function getNotifications(Request $request, int $userId)
    {
        $limit = $request->integer('limit', 20);
        
        $query = Notification::where('user_id', $userId)->latest();
        
        if ($request->has('unread_only') && $request->boolean('unread_only')) {
            $query->where('is_read', false);
        }
        
        return $query->paginate($limit);
    }

    /**
     * Get the count of unread notifications for a user.
     */
    protected function countUnread(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->count();
    }

    /**
     * Mark a specific notification as read.
     */
    protected function markNotificationRead(int $userId, string $id): bool
    {
        $notification = Notification::where('user_id', $userId)
            ->where('id', $id)
            ->first();

        if (!$notification) {
            return false;
        }

        $notification->update(['is_read' => true]);
        return true;
    }
}

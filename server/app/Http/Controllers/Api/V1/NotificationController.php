<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $notifications = UserNotification::with('event')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->paginate(20);

        $unreadCount = UserNotification::where('user_id', $userId)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $notifications,
                'unread_count' => $unreadCount,
            ],
        ]);
    }

    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $notification = UserNotification::where('user_id', $request->user()->id)->findOrFail($id);
        $notification->update(['read_at' => now()->format('Y-m-d H:i:s.u')]);

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        UserNotification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()->format('Y-m-d H:i:s.u')]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read.',
        ]);
    }
}

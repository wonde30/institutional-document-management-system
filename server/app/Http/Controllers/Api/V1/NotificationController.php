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
        $user = $request->user();
        $userId = $user->id;
        $permittedDocUnits = $user->getPermittedUnitIds('documents.view');

        $notifications = UserNotification::with(['event.document.ownerUnit', 'event.document.confidentiality', 'event.actor'])
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->paginate(20);

        $unreadCount = UserNotification::where('user_id', $userId)
            ->whereNull('read_at')
            ->count();

        $items = collect($notifications->items())->map(function ($notif) use ($user) {
            $event = $notif->event;
            $doc = $event?->document;
            $docData = null;
            $targetUrl = '/notifications';

            if ($doc) {
                $isPermitted = $doc->allowsUser($user, 'documents.view');

                if ($isPermitted) {
                    $docData = [
                        'id' => $doc->id,
                        'title' => $doc->title,
                        'reference_number' => $doc->reference_number,
                        'is_restricted' => false,
                    ];
                    $targetUrl = "/documents/{$doc->id}";
                } else {
                    $docData = [
                        'id' => $doc->id,
                        'title' => '[Restricted Institutional Record]',
                        'reference_number' => '[Restricted]',
                        'is_restricted' => true,
                    ];
                }
            }

            if ($event) {
                if (str_starts_with($event->type_code, 'correspondence.')) {
                    $targetUrl = '/correspondence';
                } elseif (str_starts_with($event->type_code, 'workflow.')) {
                    $targetUrl = $docData && ! $docData['is_restricted'] ? "/documents/{$docData['id']}" : '/tasks';
                } elseif (str_starts_with($event->type_code, 'archive.')) {
                    $targetUrl = '/archive';
                }
            }

            return [
                'id' => $notif->id,
                'event_id' => $notif->event_id,
                'user_id' => $notif->user_id,
                'read_at' => $notif->read_at,
                'created_at' => $notif->created_at,
                'target_url' => $targetUrl,
                'event' => $event ? [
                    'id' => $event->id,
                    'type_code' => $event->type_code,
                    'document_id' => $event->document_id,
                    'actor_id' => $event->actor_id,
                    'occurred_at' => $event->occurred_at,
                    'document' => $docData,
                    'actor' => $event->actor ? [
                        'id' => $event->actor->id,
                        'name' => $event->actor->name,
                    ] : null,
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                // The records are returned as a list; the paging counters are
                // not part of this envelope.
                'items' => $items,
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

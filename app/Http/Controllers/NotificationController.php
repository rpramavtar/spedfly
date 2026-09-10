<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function feed(): JsonResponse
    {
        $userId = (int) Auth::id();

        $items = AppNotification::query()
            ->where('user_id', $userId)
            ->latest()
            ->limit(10)
            ->get(['id', 'title', 'message', 'type', 'is_read', 'created_at']);

        $unreadCount = AppNotification::query()
            ->where('user_id', $userId)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'unread_count' => $unreadCount,
            'items' => $items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'message' => $item->message,
                    'type' => $item->type,
                    'is_read' => (bool) $item->is_read,
                    'created_at' => optional($item->created_at)->toDateTimeString(),
                ];
            })->values()->all(),
        ]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $userId = (int) Auth::id();

        AppNotification::query()
            ->where('user_id', $userId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json(['ok' => true]);
    }
}

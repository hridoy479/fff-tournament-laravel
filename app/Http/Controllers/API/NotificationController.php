<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    /**
     * Get user's notifications
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $perPage = $request->per_page ?? 15;
        $unreadOnly = $request->unread_only === 'true';
        
        $query = Notification::where('user_id', $user->id);
        
        if ($unreadOnly) {
            $query->where('read_at', null);
        }
        
        $notifications = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);
            
        return response()->json($notifications);
    }

    /**
     * Get unread notification count
     */
    public function unreadCount()
    {
        $user = Auth::user();
        $count = Notification::where('user_id', $user->id)
            ->where('read_at', null)
            ->count();
            
        return response()->json([
            'unread_count' => $count
        ]);
    }

    /**
     * Mark a notification as read
     */
    public function markAsRead(string $id)
    {
        $user = Auth::user();
        $notification = Notification::where('id', $id)
            ->where('user_id', $user->id)
            ->first();
            
        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found',
            ], 404);
        }
        
        $notification->update(['read_at' => now()]);
        
        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
            'notification' => $notification,
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead()
    {
        $user = Auth::user();
        
        Notification::where('user_id', $user->id)
            ->where('read_at', null)
            ->update(['read_at' => now()]);
            
        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read',
        ]);
    }

    /**
     * Delete a notification
     */
    public function destroy(string $id)
    {
        $user = Auth::user();
        $notification = Notification::where('id', $id)
            ->where('user_id', $user->id)
            ->first();
            
        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found',
            ], 404);
        }
        
        $notification->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Notification deleted',
        ]);
    }

    /**
     * Delete all read notifications
     */
    public function deleteAllRead()
    {
        $user = Auth::user();
        
        Notification::where('user_id', $user->id)
            ->whereNotNull('read_at')
            ->delete();
            
        return response()->json([
            'success' => true,
            'message' => 'All read notifications deleted',
        ]);
    }
}

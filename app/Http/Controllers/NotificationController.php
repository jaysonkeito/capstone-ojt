<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * The recipient's notification inbox — one page for every role. Review
 * decisions, pending manual entries, and flagged entries all arrive here;
 * reading is explicit (mark one or mark all), so unread counts stay
 * meaningful.
 */
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request->user()->notifications()
            ->latest()
            ->limit(50)
            ->get();

        return view('notifications.index', ['notifications' => $notifications]);
    }

    /**
     * Mark every unread notification as read.
     */
    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return redirect()->route('notifications.index')->with('status', 'All notifications marked as read.');
    }

    /**
     * Mark a single notification as read — the notification must belong
     * to the current user.
     */
    public function markRead(Request $request, DatabaseNotification $notification)
    {
        abort_unless($notification->notifiable_id === $request->user()->id, 404);

        $notification->markAsRead();

        return back();
    }

    /**
     * Remove one notification from the inbox for good — reached by the
     * delete button on wide screens or a left-swipe on a phone. The
     * notification must belong to the current user.
     */
    public function destroy(Request $request, DatabaseNotification $notification)
    {
        abort_unless($notification->notifiable_id === $request->user()->id, 404);

        $notification->delete();

        return back()->with('status', 'Notification deleted.');
    }
}

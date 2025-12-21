<?php

namespace App\Http\Controllers;

use App\Core\Models\AdminNotification;
use App\Core\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationService $notificationService
    ) {
        $this->middleware('auth:admin');
    }

    public function markAsRead(AdminNotification $notification)
    {
        // Ensure the notification belongs to the authenticated admin
        if ($notification->admin_id !== Auth::guard('admin')->id()) {
            abort(403);
        }

        $this->notificationService->markAsRead($notification);

        return redirect()->back()->with('success', __('system.notification_marked_as_read'));
    }

    public function markAllAsRead()
    {
        $admin = Auth::guard('admin')->user();
        $this->notificationService->markAllAsRead($admin);

        return redirect()->back()->with('success', __('system.all_notifications_marked_as_read'));
    }
}


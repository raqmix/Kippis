<?php

namespace App\Livewire;

use App\Core\Models\AdminNotification;
use App\Core\Services\NotificationService;
use Filament\Notifications\Notification;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class NotificationBell extends Component
{
    public $unreadCount = 0;
    public $notifications = [];

    protected $listeners = ['notificationAdded' => 'loadNotifications'];

    public function mount(): void
    {
        $this->loadNotifications();
    }

    public function loadNotifications(): void
    {
        $admin = Auth::guard('admin')->user();
        
        if ($admin) {
            $this->unreadCount = $admin->unreadNotificationsCount();
            $this->notifications = $admin->unreadNotifications()
                ->limit(10)
                ->get()
                ->map(function ($notification) {
                    return [
                        'id' => $notification->id,
                        'title' => $notification->title,
                        'message' => $notification->message,
                        'type' => $notification->type->value,
                        'action_url' => $notification->action_url,
                        'action_text' => $notification->action_text,
                        'created_at' => $notification->created_at->diffForHumans(),
                        'is_read' => $notification->isRead(),
                    ];
                })
                ->toArray();
        }
    }

    public function markAsRead($notificationId): void
    {
        $admin = Auth::guard('admin')->user();
        $notification = AdminNotification::where('admin_id', $admin->id)
            ->where('id', $notificationId)
            ->first();

        if ($notification && !$notification->isRead()) {
            app(NotificationService::class)->markAsRead($notification);
            $this->loadNotifications();
            
            Notification::make()
                ->title(__('system.notification_marked_as_read'))
                ->success()
                ->send();
        }
    }

    public function markAllAsRead(): void
    {
        $admin = Auth::guard('admin')->user();
        app(NotificationService::class)->markAllAsRead($admin);
        $this->loadNotifications();
        
        Notification::make()
            ->title(__('system.all_notifications_marked_as_read'))
            ->success()
            ->send();
    }

    public function render()
    {
        return view('livewire.notification-bell');
    }
}

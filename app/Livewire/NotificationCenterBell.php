<?php

namespace App\Livewire;

use App\Core\Models\NotificationCenter;
use Filament\Notifications\Notification;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class NotificationCenterBell extends Component
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
            $this->unreadCount = $admin->unreadNotificationsCenterCount();
            $this->notifications = NotificationCenter::forUser($admin)
                ->latest()
                ->limit(10)
                ->get()
                ->map(function ($notification) {
                    return [
                        'id' => $notification->id,
                        'title' => $notification->title,
                        'body' => $notification->body,
                        'type' => $notification->type,
                        'icon' => $notification->icon,
                        'color' => $notification->color,
                        'is_read' => $notification->is_read,
                        'action_url' => $notification->action_url,
                        'arabic_time' => $notification->arabic_time,
                    ];
                })
                ->toArray();
        }
    }

    public function markAsRead($notificationId): void
    {
        $admin = Auth::guard('admin')->user();
        $notification = NotificationCenter::forUser($admin)
            ->where('id', $notificationId)
            ->first();

        if ($notification && !$notification->is_read) {
            $notification->markAsRead();
            $this->loadNotifications();
            
            // Redirect if action_url exists
            if ($notification->action_url) {
                $this->redirect($notification->action_url, navigate: true);
            }
        }
    }

    public function markAllAsRead(): void
    {
        $admin = Auth::guard('admin')->user();
        
        if ($admin) {
            NotificationCenter::forUser($admin)
                ->where('is_read', false)
                ->update([
                    'is_read' => true,
                    'read_at' => now(),
                ]);
            
            $this->loadNotifications();
            
            Notification::make()
                ->title('تم وضع علامة على جميع الإشعارات كمقروءة')
                ->success()
                ->send();
        }
    }

    public function render()
    {
        return view('livewire.notification-center-bell');
    }
}


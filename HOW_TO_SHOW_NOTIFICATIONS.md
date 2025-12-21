# How to Show Notifications in Filament v4

## ✅ Automatic Display (No Code Needed!)

**Filament v4 automatically displays Laravel database notifications in the topbar!**

When you send a database notification using `dbNotify()`, Filament will:
1. **Automatically show a bell icon** in the topbar
2. **Display unread count badge** on the bell
3. **Show dropdown** when clicking the bell
4. **List all notifications** with unread/read states
5. **Allow marking as read** by clicking

**You don't need to write any custom code for this!**

---

## 📍 Where Notifications Appear

Notifications appear in the **Filament topbar** (top-right corner):
- Bell icon (🔔) with red badge showing unread count
- Click bell → dropdown opens showing all notifications
- Unread notifications are highlighted
- Read notifications are faded

---

## 🧪 How to Test/Trigger Notifications

### Method 1: Using the Helper Function

```php
// In any controller, resource page, or observer
use Illuminate\Support\Facades\Auth;

// Send a test notification
dbNotify()->success(
    'Test Notification',
    'This is a test notification message',
    Auth::guard('admin')->user()
);
```

### Method 2: In a Resource Page

**File:** `app/Filament/Resources/SupportTicketResource/Pages/CreateSupportTicket.php`

```php
protected function afterCreate(): void
{
    dbNotify()->success(
        __('system.created_successfully'),
        __('system.record_has_been_created'),
        Auth::guard('admin')->user(),
        static::getResource()::getUrl('view', ['record' => $this->record])
    );
}
```

### Method 3: Using Tinker (Quick Test)

```bash
php artisan tinker
```

```php
$admin = \App\Core\Models\Admin::first();
dbNotify()->info('Test', 'This is a test notification', $admin);
```

### Method 4: In an Observer

**File:** `app/Observers/SupportTicketObserver.php`

```php
public function updated(SupportTicket $ticket): void
{
    if ($ticket->wasChanged('assigned_to') && $ticket->assigned_to) {
        $admin = $ticket->assignedTo;
        
        if ($admin) {
            dbNotify()->info(
                __('system.ticket_assigned'),
                __('system.ticket_assigned_message', [
                    'id' => $ticket->id,
                    'title' => $ticket->subject
                ]),
                $admin,
                \App\Filament\Resources\SupportTicketResource::getUrl('view', ['record' => $ticket])
            );
        }
    }
}
```

---

## 👀 How to View Notifications

### 1. In Filament Topbar (Automatic)

1. **Login to admin panel**
2. **Look at top-right corner** - you'll see a bell icon 🔔
3. **If you have unread notifications**, you'll see a red badge with count
4. **Click the bell** → dropdown opens showing all notifications
5. **Click a notification** → marks as read and optionally redirects

### 2. View All Notifications Page

You have a dedicated page at: `/admin/all-notifications`

Access it via:
- Direct URL: `http://your-domain.com/admin/all-notifications`
- Or add it to navigation (currently hidden)

---

## 🔧 Verification Checklist

### ✅ Step 1: Check Admin Model

Make sure your `Admin` model uses `Notifiable` trait:

```php
// app/Core/Models/Admin.php
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use Notifiable; // ✅ This is required
}
```

### ✅ Step 2: Check Database Table

Make sure the `notifications` table exists:

```bash
php artisan migrate
```

The table should have these columns:
- `id`
- `type`
- `notifiable_type`
- `notifiable_id`
- `data` (JSON)
- `read_at` (nullable)
- `created_at`
- `updated_at`

### ✅ Step 3: Test Notification

Send a test notification:

```php
// In tinker or any controller
$admin = \App\Core\Models\Admin::first();
dbNotify()->success('Test', 'Testing notifications', $admin);
```

### ✅ Step 4: Check Topbar

1. **Refresh your Filament admin page**
2. **Look at top-right corner**
3. **You should see the bell icon** with a red badge (if unread notifications exist)
4. **Click the bell** to see notifications

---

## 🎨 Notification Types

You can send different types of notifications:

```php
// Success (Green)
dbNotify()->success('Success', 'Operation completed');

// Info (Teal)
dbNotify()->info('Information', 'System update available');

// Warning (Amber)
dbNotify()->warning('Warning', 'Please review changes');

// Danger (Red)
dbNotify()->danger('Error', 'Operation failed');
```

---

## 🔄 Real-Time Updates

Filament automatically polls for new notifications. You don't need to:
- ❌ Write custom JavaScript
- ❌ Create Livewire components
- ❌ Set up WebSockets
- ❌ Add custom routes

**Filament handles everything automatically!**

---

## 📝 Example: Complete Notification Flow

### 1. Create a Notification

```php
// In a Resource page afterCreate()
protected function afterCreate(): void
{
    $admin = Auth::guard('admin')->user();
    
    dbNotify()->success(
        'Record Created',
        'The record has been created successfully',
        $admin,
        static::getResource()::getUrl('view', ['record' => $this->record])
    );
}
```

### 2. Notification Appears Automatically

- User sees bell icon with badge
- Clicks bell → sees notification
- Clicks notification → marks as read and redirects to record

### 3. View in Database

```php
// Check notifications in database
$admin = \App\Core\Models\Admin::first();
$notifications = $admin->notifications;
$unread = $admin->unreadNotifications;
```

---

## 🐛 Troubleshooting

### Problem: Notifications not showing

**Solution:**
1. Check if `Admin` model uses `Notifiable` trait ✅
2. Check if `notifications` table exists: `php artisan migrate`
3. Check if queue is running (if using queues): `php artisan queue:work`
4. Clear cache: `php artisan config:clear && php artisan cache:clear`

### Problem: Bell icon not visible

**Solution:**
- Filament automatically shows bell when there are notifications
- If no notifications exist, bell might not be visible
- Send a test notification to verify

### Problem: Notifications not updating

**Solution:**
- Filament polls automatically (every few seconds)
- Refresh the page if needed
- Check browser console for errors

---

## 📚 Quick Reference

```php
// Send notification
dbNotify()->success('Title', 'Message', $admin, $url);

// Get notifications
$admin->notifications; // All notifications
$admin->unreadNotifications; // Unread only
$admin->unreadNotifications->count(); // Unread count

// Mark as read
$notification->markAsRead();
$admin->unreadNotifications->markAsRead(); // All
```

---

## ✨ That's It!

Filament v4 handles everything automatically. Just use `dbNotify()` and notifications will appear in the topbar!


# Database Notifications in Filament v4

## Overview

This application uses **Laravel's native database notifications** displayed in Filament's topbar with a Facebook-like UX experience.

## Architecture

### 1. Notification Classes

**Location:** `app/Notifications/`

All notifications extend `DatabaseNotification` base class:

- `SuccessNotification` - Green, check-circle icon
- `InfoNotification` - Teal, information-circle icon
- `WarningNotification` - Amber, exclamation-triangle icon
- `DangerNotification` - Red, shield-exclamation icon

### 2. Centralized Service

**Location:** `app/Core/Services/DatabaseNotificationService.php`

Provides unified interface for sending database notifications:

```php
dbNotify()->success('Title', 'Message', $admin, $url);
dbNotify()->info('Title', 'Message', $admin, $url);
dbNotify()->warning('Title', 'Message', $admin, $url);
dbNotify()->danger('Title', 'Message', $admin, $url);
```

### 3. Helper Function

**Location:** `app/Helpers/NotificationHelper.php`

Global helper for easy access:

```php
dbNotify()->success('Record Created', 'The record has been created successfully');
```

## Notification Data Structure

Each notification contains:

```php
[
    'type' => 'success|info|warning|danger',
    'title' => 'Short, bold title',
    'message' => '1 line max message',
    'icon' => 'heroicon-o-check-circle',
    'url' => 'optional-redirect-url',
    'data' => ['additional' => 'data'],
    'created_at' => 'ISO8601 timestamp',
]
```

## Filament Display

Filament v4 automatically displays Laravel database notifications in the topbar:

- **Bell icon** with unread count badge
- **Dropdown list** on click
- **Unread notifications** highlighted with background color
- **Read notifications** faded/muted
- **Click notification** → marks as read and optionally redirects
- **Time ago** display (e.g., "2 minutes ago")

## Usage Examples

### 1. CRUD Operations

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

### 2. Role & Permission Changes

```php
// In RoleResource after update
dbNotify()->warning(
    __('system.role_permissions_modified'),
    __('system.role_has_been_updated', ['name' => $role->name]),
    Auth::guard('admin')->user()
);
```

### 3. Channel Configuration Updates

```php
// In ChannelResource after save
dbNotify()->info(
    __('system.channel_updated'),
    __('system.channel_configuration_saved'),
    Auth::guard('admin')->user(),
    static::getResource()::getUrl('view', ['record' => $this->record])
);
```

### 4. Support Ticket Replies

```php
// When admin replies to ticket
dbNotify()->info(
    __('system.reply_sent'),
    __('system.your_reply_has_been_sent'),
    $admin,
    \App\Filament\Resources\SupportTicketResource::getUrl('view', ['record' => $ticket])
);
```

### 5. Security Alerts

```php
// Login from new IP (in HandleAdminLogin listener)
if ($isNewIp) {
    dbNotify()->warning(
        __('system.new_login_location'),
        __('system.login_from_new_ip', ['ip' => $ipAddress]),
        $admin
    );
}
```

### 6. Ticket Assignment

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

### 7. Multiple Admins

```php
$admins = Admin::where('is_active', true)->get();
dbNotify()->notifyMultiple(
    $admins->all(),
    'info',
    'System Maintenance',
    'Scheduled maintenance in 1 hour'
);
```

### 8. By Permission

```php
dbNotify()->notifyByPermission(
    'manage_settings',
    'warning',
    'Settings Changed',
    'System settings have been modified'
);
```

## Facebook-Like UX Features

### 1. Unread Count Badge
- Red badge on bell icon
- Shows unread count (max 99+)
- Updates in real-time

### 2. Dropdown List
- Opens on bell click
- Shows latest notifications
- Scrollable list

### 3. Visual States
- **Unread:** Highlighted background (#F1F2F8), bold text
- **Read:** Faded background, muted text (#6B7280)

### 4. Interaction
- Click notification → marks as read
- Optional redirect to URL
- "Mark all as read" button

### 5. Time Display
- Shows "time ago" format
- Updates dynamically

## Design & Colors

Colors match the application theme:

- **Primary:** #7B6CF6 (Purple)
- **Accent:** #4FD1C5 (Teal)
- **Success:** #22C55E (Green)
- **Warning:** #F59E0B (Amber)
- **Danger:** #EF4444 (Red)
- **Unread Background:** #F1F2F8
- **Text Muted:** #6B7280

## Best Practices

### 1. Keep Messages Concise
✅ Good: "Settings saved successfully"
❌ Bad: "The settings you just modified have been successfully saved to the database and are now active"

### 2. No Sensitive Data
✅ Good: "Login attempt failed"
❌ Bad: "Login failed for email: admin@example.com with password: 123456"

### 3. Use Translations
Always use translation keys:
```php
dbNotify()->success(
    __('system.created_successfully'),
    __('system.record_has_been_created')
);
```

### 4. Appropriate Types
- **Success:** Positive outcomes (create, update, delete)
- **Info:** General information (updates, replies)
- **Warning:** Attention needed (permissions, validation)
- **Danger:** Errors, security alerts

### 5. Provide URLs
Always provide redirect URLs when relevant:
```php
dbNotify()->info(
    'Ticket Assigned',
    'You have been assigned ticket #123',
    $admin,
    \App\Filament\Resources\SupportTicketResource::getUrl('view', ['record' => $ticket])
);
```

## Integration with Activity Logs

Database notifications complement activity logs:

- **Notifications:** Real-time alerts for admins
- **Activity Logs:** Historical record of all actions

Both are created via Events + Listeners for consistency.

## Security Considerations

1. **No Sensitive Data:** Never include passwords, tokens, or secrets
2. **Generic Errors:** Use generic error messages for security
3. **Permission Checks:** Verify permissions before sending notifications
4. **Audit Trail:** All notifications are logged in activity logs

## Testing

```php
// In tests
$admin = Admin::factory()->create();
dbNotify()->success('Test', 'This is a test', $admin);

$this->assertDatabaseHas('notifications', [
    'notifiable_type' => Admin::class,
    'notifiable_id' => $admin->id,
    'data->title' => 'Test',
]);
```

## Migration from Custom System

If migrating from the custom `AdminNotification` system:

1. Replace `NotificationService` calls with `dbNotify()`
2. Update observers to use database notifications
3. Remove custom notification bell component (Filament handles it)
4. Keep activity logs for historical data

## Files Created

- `app/Notifications/DatabaseNotification.php` - Base notification class
- `app/Notifications/SuccessNotification.php` - Success notifications
- `app/Notifications/InfoNotification.php` - Info notifications
- `app/Notifications/WarningNotification.php` - Warning notifications
- `app/Notifications/DangerNotification.php` - Danger notifications
- `app/Core/Services/DatabaseNotificationService.php` - Centralized service
- `app/Core/Listeners/SendDatabaseNotification.php` - Event listener

## Next Steps

1. Use `dbNotify()` throughout the application
2. Replace custom notification calls
3. Test notifications in different scenarios
4. Customize Filament's notification display if needed


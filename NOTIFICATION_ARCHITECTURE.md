# Filament Notification Architecture

## Overview

This application uses **Filament's native notification system** exclusively. All notifications are server-driven and follow Filament's design patterns.

## Architecture

### 1. Centralized Service

**Location:** `app/Core/Services/FilamentNotificationService.php`

This service provides a unified interface for all notifications:

```php
// Success (auto-dismiss after 5s)
notify()->success('Operation Successful', 'The record has been saved.');

// Warning (requires manual dismiss)
notify()->warning('Attention Required', 'Please review the changes.');

// Danger (requires manual dismiss)
notify()->danger('Error Occurred', 'Unable to complete the operation.');

// Info (auto-dismiss after 5s)
notify()->info('Information', 'System will be updated shortly.');
```

### 2. Helper Function

**Location:** `app/Helpers/NotificationHelper.php`

Global helper function for easy access:

```php
notify()->success('Title', 'Body');
```

**Note:** Add to `composer.json` autoload files:
```json
"autoload": {
    "files": [
        "app/Helpers/NotificationHelper.php"
    ]
}
```

### 3. Event-Driven Notifications

**Location:** `app/Core/Listeners/SendFilamentNotification.php`

Notifications are sent via event listeners for:
- Login success/failure
- Security events
- System alerts

## Notification Types

### Success
- **Color:** #22C55E (Green)
- **Auto-dismiss:** Yes (5 seconds)
- **Use cases:** CRUD operations, successful saves, login success

### Warning
- **Color:** #F59E0B (Amber)
- **Auto-dismiss:** No (persistent)
- **Use cases:** Permission warnings, validation issues, attention required

### Danger
- **Color:** #EF4444 (Red)
- **Auto-dismiss:** No (persistent)
- **Use cases:** Errors, failed operations, security alerts

### Info
- **Color:** #7B6CF6 (Primary Purple)
- **Auto-dismiss:** Yes (5 seconds)
- **Use cases:** System updates, informational messages

## Usage Examples

### 1. CRUD Operations

```php
// In Resource Pages
protected function afterCreate(): void
{
    notify()->success(
        __('system.created_successfully'),
        __('system.record_has_been_created')
    );
}

protected function afterUpdate(): void
{
    notify()->success(
        __('system.updated_successfully'),
        __('system.record_has_been_updated')
    );
}

protected function afterDelete(): void
{
    notify()->success(
        __('system.deleted_successfully'),
        __('system.record_has_been_deleted')
    );
}
```

### 2. Login Events

```php
// Automatically handled by SendFilamentNotification listener
// On successful login:
notify()->success('Login Successful', 'Welcome back!');

// On failed login (to security admins):
notify()->danger('Failed Login Attempt', 'Invalid credentials attempted');
```

### 3. Permission Denied

```php
// In middleware or policies
if (!$admin->can('manage_settings')) {
    notify()->warning(
        __('system.permission_denied'),
        __('system.you_do_not_have_permission')
    );
    return redirect()->back();
}
```

### 4. Channel Configuration

```php
// In ChannelResource
public function afterSave(): void
{
    notify()->success(
        __('system.channel_updated'),
        __('system.channel_configuration_saved')
    );
}
```

### 5. Support Ticket Replies

```php
// In SupportTicketResource
public function afterReply(): void
{
    notify()->info(
        __('system.reply_sent'),
        __('system.your_reply_has_been_sent')
    );
}
```

### 6. Security Events

```php
// Role/Permission changes
notify()->warning(
    __('system.security_alert'),
    __('system.role_permissions_modified')
);
```

### 7. With Action Button

```php
notify()->withAction(
    'success',
    'Ticket Assigned',
    'You have been assigned a new ticket',
    'View Ticket',
    route('filament.admin.resources.support-tickets.view', $ticket)
);
```

### 8. Database Notifications (Persistent)

```php
// Store in database for later viewing
notify()->info(
    'System Maintenance',
    'Scheduled maintenance in 1 hour',
    null, // Current user
    true  // Persist to database
);
```

## Database Notifications

Filament automatically stores notifications in the `notifications` table when using `sendToDatabase()`.

### Accessing Database Notifications

```php
$admin = Auth::guard('admin')->user();
$unreadNotifications = $admin->unreadNotifications;
```

### Displaying in Topbar

Filament automatically displays database notifications in the topbar notification bell.

## Best Practices

### 1. Keep Messages Concise
✅ Good: "Settings saved successfully"
❌ Bad: "The settings you just modified have been successfully saved to the database"

### 2. No Sensitive Data
✅ Good: "Login attempt failed"
❌ Bad: "Login failed for email: admin@example.com with password: 123456"

### 3. Use Translations
Always use translation keys:
```php
notify()->success(__('system.saved'), __('system.record_updated'));
```

### 4. Appropriate Types
- **Success:** Positive outcomes
- **Warning:** Attention needed, but not critical
- **Danger:** Errors, failures, security issues
- **Info:** General information

### 5. Auto-dismiss Rules
- **Success/Info:** Auto-dismiss (non-critical)
- **Warning/Danger:** Persistent (requires attention)

## Integration Points

### 1. Resources
- `afterCreate()`, `afterUpdate()`, `afterDelete()`
- Form validation errors
- Bulk actions

### 2. Pages
- Form submissions
- Action buttons
- Custom operations

### 3. Middleware
- Permission checks
- Authentication failures
- Security violations

### 4. Events & Listeners
- Login/logout events
- Security events
- System events

## Color Customization

Colors are defined in `app/Providers/Filament/AdminPanelProvider.php`:

```php
->colors([
    'primary' => Color::hex('#7B6CF6'),
    'success' => Color::hex('#22C55E'),
    'warning' => Color::hex('#F59E0B'),
    'danger' => Color::hex('#EF4444'),
])
```

## Security Considerations

1. **No Sensitive Data:** Never include passwords, tokens, or secrets
2. **Generic Errors:** Use generic error messages for security
3. **Permission Checks:** Verify permissions before sending notifications
4. **Audit Trail:** All notifications are logged in activity logs

## Testing

```php
// In tests
notify()->success('Test', 'This is a test');
$this->assertNotificationSent('success', 'Test');
```

## Migration from Custom System

If migrating from the custom `AdminNotification` system:

1. Keep database notifications for persistent alerts
2. Use Filament notifications for real-time feedback
3. Gradually replace custom notification calls
4. Maintain backward compatibility during transition


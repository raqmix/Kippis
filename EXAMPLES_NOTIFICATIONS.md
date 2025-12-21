# Filament Notification Usage Examples

## Quick Reference

```php
// Success (auto-dismiss)
notify()->success('Title', 'Body');

// Warning (persistent)
notify()->warning('Title', 'Body');

// Danger (persistent)
notify()->danger('Title', 'Body');

// Info (auto-dismiss)
notify()->info('Title', 'Body');
```

## Real-World Examples

### 1. Resource CRUD Operations

**File:** `app/Filament/Resources/SupportTicketResource/Pages/CreateSupportTicket.php`

```php
<?php

namespace App\Filament\Resources\SupportTicketResource\Pages;

use App\Filament\Resources\SupportTicketResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSupportTicket extends CreateRecord
{
    protected static string $resource = SupportTicketResource::class;

    protected function afterCreate(): void
    {
        notify()->success(
            __('system.created_successfully'),
            __('system.record_has_been_created')
        );
    }
}
```

**File:** `app/Filament/Resources/SupportTicketResource/Pages/EditSupportTicket.php`

```php
<?php

namespace App\Filament\Resources\SupportTicketResource\Pages;

use App\Filament\Resources\SupportTicketResource;
use Filament\Resources\Pages\EditRecord;

class EditSupportTicket extends EditRecord
{
    protected static string $resource = SupportTicketResource::class;

    protected function afterSave(): void
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
}
```

### 2. Permission Checks

**File:** `app/Http/Middleware/CheckPermission.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        if (!auth('admin')->user()->can($permission)) {
            notify()->warning(
                __('system.permission_denied'),
                __('system.you_do_not_have_permission')
            );
            
            return redirect()->back();
        }

        return $next($request);
    }
}
```

### 3. Settings Updates

**File:** `app/Filament/Resources/SettingResource/Pages/ManageSettings.php`

```php
protected function getHeaderActions(): array
{
    return [
        Actions\Action::make('save')
            ->label(__('system.save'))
            ->action(function () {
                // Save logic...
                
                notify()->success(
                    __('system.settings_saved_successfully'),
                    __('system.changes_have_been_applied')
                );
            }),
    ];
}
```

### 4. Channel Configuration

**File:** `app/Filament/Resources/ChannelResource/Pages/EditChannel.php`

```php
protected function afterSave(): void
{
    notify()->success(
        __('system.channel_updated'),
        __('system.channel_configuration_saved')
    );
}
```

### 5. Support Ticket Replies

**File:** `app/Filament/Resources/SupportTicketResource/Pages/ViewSupportTicket.php`

```php
protected function getHeaderActions(): array
{
    return [
        Actions\Action::make('reply')
            ->form([
                Forms\Components\Textarea::make('message')
                    ->required()
                    ->rows(5),
            ])
            ->action(function (array $data) {
                // Send reply logic...
                
                notify()->info(
                    __('system.reply_sent'),
                    __('system.your_reply_has_been_sent')
                );
            }),
    ];
}
```

### 6. Security Events

**File:** `app/Filament/Resources/RoleResource/Pages/EditRole.php`

```php
protected function afterSave(): void
{
    notify()->warning(
        __('system.security_alert'),
        __('system.role_permissions_modified')
    );
}
```

### 7. With Action Button

```php
notify()->withAction(
    'success',
    'Ticket Assigned',
    'You have been assigned ticket #123',
    'View Ticket',
    \App\Filament\Resources\SupportTicketResource::getUrl('view', ['record' => $ticket])
);
```

### 8. Database Notifications (Persistent)

```php
// Store in database for later viewing in topbar
notify()->info(
    'System Maintenance',
    'Scheduled maintenance in 1 hour',
    null, // Current user
    true  // Persist to database
);
```

### 9. Multiple Admins

```php
$admins = Admin::where('is_active', true)->get();
notify()->notifyMultiple(
    $admins->all(),
    'info',
    'System Update',
    'The system will be updated tonight at 2 AM'
);
```

### 10. By Permission

```php
notify()->notifyByPermission(
    'manage_settings',
    'warning',
    'Settings Changed',
    'System settings have been modified'
);
```

## Integration with Existing Code

### Replace Custom Notifications

**Before:**
```php
$this->notify('success', 'Settings saved');
```

**After:**
```php
notify()->success(
    __('system.settings_saved_successfully')
);
```

### In Observers

**File:** `app/Observers/SupportTicketObserver.php`

```php
public function updated(SupportTicket $ticket): void
{
    if ($ticket->wasChanged('assigned_to') && $ticket->assigned_to) {
        $admin = $ticket->assignedTo;
        
        if ($admin) {
            notify()->withAction(
                'info',
                __('system.ticket_assigned'),
                __('system.ticket_assigned_message', [
                    'id' => $ticket->id,
                    'title' => $ticket->subject
                ]),
                __('system.view_ticket'),
                \App\Filament\Resources\SupportTicketResource::getUrl('view', ['record' => $ticket]),
                $admin,
                true // Persist to database
            );
        }
    }
}
```

## Best Practices

1. **Always use translations:**
   ```php
   notify()->success(__('system.saved'));
   ```

2. **Keep messages concise:**
   ```php
   ✅ notify()->success('Settings saved');
   ❌ notify()->success('The settings you just modified have been successfully saved to the database');
   ```

3. **No sensitive data:**
   ```php
   ✅ notify()->danger('Login failed');
   ❌ notify()->danger('Login failed for admin@example.com with password 123456');
   ```

4. **Use appropriate types:**
   - Success: Positive outcomes
   - Warning: Attention needed
   - Danger: Errors, failures
   - Info: General information

5. **Auto-dismiss rules:**
   - Success/Info: Auto-dismiss (non-critical)
   - Warning/Danger: Persistent (requires attention)


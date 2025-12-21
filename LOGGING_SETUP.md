# Logging Configuration

Telescope has been removed and replaced with Laravel's built-in logging system.

## Log Channels

The system uses the following log channels:

1. **Default Stack** (`stack`)
   - Combines multiple channels
   - Default: `single` channel

2. **Activity Logs** (`activity`)
   - Location: `storage/logs/activity.log`
   - Retention: 30 days
   - Used for: Admin activities, CRUD operations, system events

3. **Security Logs** (`security`)
   - Location: `storage/logs/security.log`
   - Retention: 90 days
   - Used for: Failed logins, security events, suspicious activities

4. **Daily Logs** (`daily`)
   - Location: `storage/logs/laravel-YYYY-MM-DD.log`
   - Retention: 14 days (configurable)
   - Used for: General application logs

## Usage

### Logging Activity Events

```php
use Illuminate\Support\Facades\Log;

Log::channel('activity')->info('Admin created', [
    'admin_id' => $admin->id,
    'action' => 'create',
    'model' => 'Admin',
]);
```

### Logging Security Events

```php
Log::channel('security')->warning('Failed login attempt', [
    'email' => $email,
    'ip' => request()->ip(),
    'user_agent' => request()->userAgent(),
]);
```

## Viewing Logs

### Using Laravel Pail (Recommended)
```bash
php artisan pail
```

### Using Tail (Linux/Mac)
```bash
tail -f storage/logs/activity.log
tail -f storage/logs/security.log
```

### Using PowerShell (Windows)
```powershell
Get-Content storage/logs/activity.log -Wait -Tail 50
Get-Content storage/logs/security.log -Wait -Tail 50
```

## Database Logs

The system also maintains logs in the database:
- `activity_logs` table - Detailed activity tracking
- `security_logs` table - Security event tracking
- `login_attempts` table - Login attempt history

These can be viewed in the Filament dashboard under:
- **Monitoring** → Activity Logs
- **Security** → Security Logs
- **Security** → Login History

## Configuration

Edit `config/logging.php` to customize:
- Log retention days
- Log levels
- Log paths
- Additional channels

## Benefits Over Telescope

1. **Lighter Weight** - No additional database tables
2. **Better Performance** - File-based logging is faster
3. **Easier Deployment** - No Telescope installation needed
4. **Standard Laravel** - Uses built-in logging features
5. **Better for Production** - File logs are easier to manage


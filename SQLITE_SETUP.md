# SQLite Database Setup

The SYSTEM CORE is now configured to use SQLite as the default database.

## Configuration

- **Default Connection**: SQLite (configured in `config/database.php`)
- **Database File**: `database/database.sqlite`
- **Foreign Keys**: Enabled by default
- **Telescope**: Configured to use SQLite connection

## Migrations Updated

All migrations have been updated for SQLite compatibility:
- ENUM columns replaced with string columns (SQLite doesn't support ENUM natively)
- JSON columns work automatically (stored as TEXT, Laravel handles conversion)
- Foreign key constraints enabled

## Setup Steps

1. **Ensure SQLite file exists**:
   ```bash
   touch database/database.sqlite
   ```
   (Already created)

2. **Run migrations**:
   ```bash
   php artisan migrate
   ```

3. **Seed database**:
   ```bash
   php artisan db:seed
   ```

4. **Install Telescope** (if not already done):
   ```bash
   php artisan telescope:install
   php artisan migrate
   ```

## Environment Variables

Make sure your `.env` file has:
```env
DB_CONNECTION=sqlite
# DB_DATABASE is optional - defaults to database/database.sqlite
```

## Notes

- SQLite is perfect for development and small to medium applications
- For production with high traffic, consider MySQL/PostgreSQL
- JSON columns are stored as TEXT but work seamlessly with Laravel
- All enum validations are handled at the application level (models/forms)


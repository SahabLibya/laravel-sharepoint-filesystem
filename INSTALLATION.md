# Installation Guide

## For New Laravel Projects

### 1. Install via Composer

If you publish this package to a repository:

```bash
composer require sahablibya/laravel-sharepoint-filesystem
```

Or install from a local path:

```bash
# In your composer.json
{
    "repositories": [
        {
            "type": "path",
            "url": "../path-to-package/laravel-sharepoint-filesystem"
        }
    ],
    "require": {
        "sahablibya/laravel-sharepoint-filesystem": "@dev"
    }
}
```

Then run:

```bash
composer update sahablibya/laravel-sharepoint-filesystem
```

### 2. Configure Environment Variables

Add to your `.env` file:

```env
GRAPH_CLIENT_ID=your-azure-client-id
GRAPH_CLIENT_SECRET=your-azure-client-secret
GRAPH_TENANT_ID=your-azure-tenant-id
SHAREPOINT_DRIVE_ID=your-sharepoint-drive-id
SHAREPOINT_PREFIX=backups  # Optional: subdirectory
```

### 3. Add Disk Configuration

Add to `config/filesystems.php`:

```php
'disks' => [
    // ... other disks

    'sharepoint' => [
        'driver' => 'sharepoint',
        'client_id' => env('GRAPH_CLIENT_ID'),
        'client_secret' => env('GRAPH_CLIENT_SECRET'),
        'tenant_id' => env('GRAPH_TENANT_ID', 'common'),
        'drive_id' => env('SHAREPOINT_DRIVE_ID'),
        'prefix' => env('SHAREPOINT_PREFIX', ''),
        'throw' => false,
    ],
],
```

### 4. Test the Connection

```php
use Illuminate\Support\Facades\Storage;

// Test write
Storage::disk('sharepoint')->put('test.txt', 'Hello SharePoint!');

// Test read
$content = Storage::disk('sharepoint')->get('test.txt');

// Test delete
Storage::disk('sharepoint')->delete('test.txt');

echo "SharePoint integration successful!";
```

## Using with Spatie Laravel Backup

### 1. Install Spatie Backup

```bash
composer require spatie/laravel-backup
php artisan vendor:publish --provider="Spatie\Backup\BackupServiceProvider"
```

### 2. Configure Backup Destination

In `config/backup.php`:

```php
'destination' => [
    'disks' => [
        'local',
        'sharepoint',  // Add SharePoint
    ],
],
```

### 3. Run Backup

```bash
# Full backup (database + files)
php artisan backup:run

# Database only
php artisan backup:run --only-db

# Files only
php artisan backup:run --only-files

# List backups
php artisan backup:list
```

## Troubleshooting

### Permission Issues

Make sure your Azure app has these permissions with admin consent:
- `Files.ReadWrite.All`
- `Sites.ReadWrite.All` (for SharePoint)

### Clear Cache

After configuration changes:

```bash
php artisan config:clear
php artisan cache:clear
```

### Timeout Issues

For large files (>30MB), the package automatically sets a 5-minute timeout.

## Features

✅ Automatic token management (no manual OAuth)  
✅ Support for SharePoint Document Libraries  
✅ Support for OneDrive for Business  
✅ Token caching (58 minutes)  
✅ Large file support (5-minute timeout)  
✅ Compatible with Laravel 10, 11, 12  
✅ Works with Spatie Laravel Backup  

## Support

For issues or questions, check the main README.md file.


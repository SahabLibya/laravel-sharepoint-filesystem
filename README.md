# Laravel SharePoint/OneDrive Filesystem Driver

[![Latest Version on Packagist](https://img.shields.io/packagist/v/sahablibya/laravel-sharepoint-filesystem.svg?style=flat-square)](https://packagist.org/packages/sahablibya/laravel-sharepoint-filesystem)
[![Total Downloads](https://img.shields.io/packagist/dt/sahablibya/laravel-sharepoint-filesystem.svg?style=flat-square)](https://packagist.org/packages/sahablibya/laravel-sharepoint-filesystem)
[![License](https://img.shields.io/packagist/l/sahablibya/laravel-sharepoint-filesystem.svg?style=flat-square)](https://packagist.org/packages/sahablibya/laravel-sharepoint-filesystem)

A Laravel filesystem driver for SharePoint and OneDrive using Microsoft Graph API. It supports client credentials for unattended SharePoint and OneDrive for Business access, plus device-code sign-in for personal and business OneDrive accounts.

## ✨ Features

- ✅ **Application-only OAuth** - Automatic token management with client credentials flow
- ✅ **SharePoint Document Libraries** - Direct access to your SharePoint sites
- ✅ **Personal and Business OneDrive** - Connect a Microsoft account with device-code sign-in
- ✅ **Automatic Token Refresh** - Handles token expiry seamlessly with smart caching
- ✅ **Laravel 10, 11, 12, 13** - Compatible with modern Laravel versions
- ✅ **Flysystem v3** - Built on the latest Flysystem architecture
- ✅ **Large File Support** - Optimized for files up to 250MB
- ✅ **Safe Copy & Move** - Monitors Microsoft Graph copy jobs before completing moves
- ✅ **Path-Safe Operations** - Handles spaces, special characters, and Unicode file names
- ✅ **Production Ready** - Battle-tested in real-world applications
- ✅ **Spatie Backup Compatible** - Perfect for automated backups to SharePoint

## 📋 Requirements

- PHP 8.1 or higher (Laravel 13 requires PHP 8.3+)
- Laravel 10.x, 11.x, 12.x, or 13.x
- Microsoft Entra app registration with appropriate permissions

## 📦 Installation

Install via Composer:

```bash
composer require sahablibya/laravel-sharepoint-filesystem
```

The service provider will be automatically registered via Laravel's package discovery.

## ⚙️ Configuration

### Step 1: Azure App Registration

1. Go to [Azure Portal](https://portal.azure.com/)
2. Navigate to **Azure Active Directory** → **App registrations**
3. Click **New registration**
4. Enter a name (e.g., "Laravel SharePoint Integration")
5. Click **Register**
6. Note your **Application (client) ID** and **Directory (tenant) ID**

### Step 2: Create Client Secret

1. In your app registration, go to **Certificates & secrets**
2. Click **New client secret**
3. Add a description and set expiration
4. Click **Add**
5. **⚠️ Copy the secret value immediately** (you won't see it again!)

### Step 3: Grant API Permissions

1. Go to **API permissions**
2. Click **Add a permission** → **Microsoft Graph** → **Application permissions**
3. Add these permissions:
   - `Files.ReadWrite.All` - Read and write files in all site collections
   - `Sites.ReadWrite.All` - Read and write items in all site collections
4. Click **Grant admin consent** (requires admin privileges)
5. Wait 2-5 minutes for permissions to propagate

### Step 4: Get SharePoint Drive ID

To use a specific SharePoint document library, you need the drive ID:

#### Option A: Using Microsoft Graph Explorer

1. Go to [Graph Explorer](https://developer.microsoft.com/en-us/graph/graph-explorer)
2. Sign in with your account
3. Find your site: `GET https://graph.microsoft.com/v1.0/sites?search=YourSiteName`
4. Get drives for that site: `GET https://graph.microsoft.com/v1.0/sites/{site-id}/drives`
5. Copy the `id` of your desired document library

#### Option B: Using PowerShell

```powershell
Connect-PnPOnline -Url "https://yourtenant.sharepoint.com/sites/yoursite"
Get-PnPList | Where-Object {$_.BaseTemplate -eq 101}
```

### Step 5: Environment Configuration

Add these variables to your `.env` file:

```env
GRAPH_CLIENT_ID=your-application-client-id
GRAPH_CLIENT_SECRET=your-client-secret-value
GRAPH_TENANT_ID=your-tenant-id

# Required: Specify the SharePoint document library
SHAREPOINT_DRIVE_ID=your-drive-id

# Optional: Prefix path within the drive
SHAREPOINT_PREFIX=backups

# Optional: Tune Microsoft Graph async copy monitoring
SHAREPOINT_COPY_MONITOR_TIMEOUT=300
SHAREPOINT_COPY_MONITOR_INTERVAL_MS=1000
```

### Step 6: Register Filesystem Disk

Add the SharePoint disk to your `config/filesystems.php`:

```php
'disks' => [
    // ... other disks

    'sharepoint' => [
        'driver' => 'sharepoint',
        'client_id' => env('GRAPH_CLIENT_ID'),
        'client_secret' => env('GRAPH_CLIENT_SECRET'),
        'tenant_id' => env('GRAPH_TENANT_ID', 'common'),
        'drive_id' => env('SHAREPOINT_DRIVE_ID'),
        'prefix' => env('SHAREPOINT_PREFIX', ''), // Optional
        'copy_monitor_timeout' => env('SHAREPOINT_COPY_MONITOR_TIMEOUT', 300),
        'copy_monitor_interval_ms' => env('SHAREPOINT_COPY_MONITOR_INTERVAL_MS', 1000),
        'throw' => false,
    ],
],
```

## Personal OneDrive

Personal OneDrive uses delegated device-code authentication. The Microsoft account owner signs in once, and the package stores the resulting refresh token encrypted with Laravel's `APP_KEY`.

### Register a Public Client

1. Create a Microsoft Entra app registration.
2. Select an account type that includes **personal Microsoft accounts**.
3. Under **Authentication** → **Advanced settings**, enable **Allow public client flows**.
4. Add the Microsoft Graph delegated permission `Files.ReadWrite`.
5. Copy the **Application (client) ID**.

A client secret, tenant ID, and drive ID are not required for this mode. Microsoft still requires a public client ID to identify the application; the system owner can configure that value once for all users of the system.

### Configure the Disk

```env
ONEDRIVE_AUTH_MODE=device_code
ONEDRIVE_CLIENT_ID=your-public-application-client-id
ONEDRIVE_PREFIX=backups
```

Add the disk to `config/filesystems.php`:

```php
'onedrive' => [
    'driver' => 'onedrive',
    'auth_mode' => 'device_code',
    'client_id' => env('ONEDRIVE_CLIENT_ID'),
    'tenant_id' => 'consumers', // Use "common" to also allow work accounts
    'token_key' => 'personal-backups',
    'prefix' => env('ONEDRIVE_PREFIX', 'backups'),
    'throw' => false,
],
```

Connect the account once:

```bash
php artisan onedrive:connect
```

The command displays a Microsoft URL and code. After sign-in, scheduled backups can refresh their access token without user interaction. Tokens are encrypted under `storage/app/onedrive-tokens` by default. Set a unique `token_key` for each OneDrive account when configuring multiple disks.

## 🚀 Usage

### Basic Operations

```php
use Illuminate\Support\Facades\Storage;

// Write a file
Storage::disk('sharepoint')->put('documents/report.pdf', $contents);

// Write from a stream (memory efficient for large files)
$stream = fopen('/path/to/large-file.zip', 'r');
Storage::disk('sharepoint')->writeStream('backups/large-file.zip', $stream);

// Read a file
$contents = Storage::disk('sharepoint')->get('documents/report.pdf');

// Read as stream
$stream = Storage::disk('sharepoint')->readStream('documents/report.pdf');

// Check if file exists
if (Storage::disk('sharepoint')->exists('documents/report.pdf')) {
    // File exists
}

// Delete a file
Storage::disk('sharepoint')->delete('documents/report.pdf');

// Delete multiple files
Storage::disk('sharepoint')->delete(['file1.pdf', 'file2.pdf']);

// Copy a file
Storage::disk('sharepoint')->copy('old.pdf', 'new.pdf');

// Move a file
Storage::disk('sharepoint')->move('old-location.pdf', 'new-location.pdf');
```

### Directory Operations

```php
// Create a directory
Storage::disk('sharepoint')->makeDirectory('documents/2024');

// List files in a directory
$files = Storage::disk('sharepoint')->files('documents');

// List all files recursively
$files = Storage::disk('sharepoint')->allFiles('documents');

// List directories
$directories = Storage::disk('sharepoint')->directories('documents');

// List all directories recursively
$directories = Storage::disk('sharepoint')->allDirectories('documents');

// Delete a directory
Storage::disk('sharepoint')->deleteDirectory('old-documents');
```

### File Metadata

```php
// Get file size
$size = Storage::disk('sharepoint')->size('documents/report.pdf');

// Get last modified time
$timestamp = Storage::disk('sharepoint')->lastModified('documents/report.pdf');

// Get MIME type
$mimeType = Storage::disk('sharepoint')->mimeType('documents/report.pdf');
```

### URLs & Downloads

```php
// Store an uploaded file
$path = $request->file('document')->store('uploads', 'sharepoint');

// Download a file
return Storage::disk('sharepoint')->download('documents/report.pdf');

// Download with custom name
return Storage::disk('sharepoint')->download('documents/report.pdf', 'custom-name.pdf');
```

## 🔄 Using with Spatie Laravel Backup

Perfect integration with [Spatie Laravel Backup](https://github.com/spatie/laravel-backup):

```php
// config/backup.php

'destination' => [
    'disks' => [
        'local',
        'onedrive', // Or "sharepoint" for an application-only disk
    ],
],
```

Run backups:

```bash
# Full backup (database + files)
php artisan backup:run

# Database only
php artisan backup:run --only-db

# List backups
php artisan backup:list

# Clean old backups
php artisan backup:clean
```

## 🔧 Advanced Configuration

### Multiple SharePoint Sites

```php
'disks' => [
    'sharepoint-hr' => [
        'driver' => 'sharepoint',
        'client_id' => env('GRAPH_CLIENT_ID'),
        'client_secret' => env('GRAPH_CLIENT_SECRET'),
        'tenant_id' => env('GRAPH_TENANT_ID'),
        'drive_id' => 'hr-drive-id',
        'prefix' => 'employee-files',
    ],
    
    'sharepoint-finance' => [
        'driver' => 'sharepoint',
        'client_id' => env('GRAPH_CLIENT_ID'),
        'client_secret' => env('GRAPH_CLIENT_SECRET'),
        'tenant_id' => env('GRAPH_TENANT_ID'),
        'drive_id' => 'finance-drive-id',
        'prefix' => 'reports',
    ],
],
```

### Using OneDrive

For application-only OneDrive for Business access, use the existing client credentials mode and provide the user's drive ID:

```php
'onedrive' => [
    'driver' => 'onedrive',
    'auth_mode' => 'client_credentials',
    'client_id' => env('GRAPH_CLIENT_ID'),
    'client_secret' => env('GRAPH_CLIENT_SECRET'),
    'tenant_id' => env('GRAPH_TENANT_ID'),
    'drive_id' => env('ONEDRIVE_DRIVE_ID'),
    'prefix' => env('ONEDRIVE_PREFIX', ''),
],
```

### Token Caching

Client-credentials access tokens are cached for 58 minutes. Device-code connections store an encrypted refresh token and request a new access token when the current token is close to expiry.

### Copy Monitoring

Microsoft Graph copy operations run asynchronously. This package waits for Graph's copy monitor to report completion before `copy()` returns. Because `move()` uses copy followed by delete, the source file is only deleted after the copy is confirmed complete.

You can tune the monitor wait behavior per disk:

```php
'copy_monitor_timeout' => 300, // seconds
'copy_monitor_interval_ms' => 1000, // milliseconds
```

## 🐛 Troubleshooting

### Permission Errors

**Error:** "Access denied" or "403 Forbidden"

**Solutions:**
1. Verify `Files.ReadWrite.All` and `Sites.ReadWrite.All` permissions are added
2. Ensure **admin consent is granted** (look for green checkmarks in Azure Portal)
3. Wait 2-5 minutes after granting consent for changes to propagate
4. Clear Laravel cache: `php artisan cache:clear`

### Authentication Errors

**Error:** "Failed to obtain access token" or "invalid_client"

**Solutions:**
1. Verify `GRAPH_CLIENT_ID` matches your app registration's Application ID
2. Verify `GRAPH_CLIENT_SECRET` is correct (they expire!)
3. Check `GRAPH_TENANT_ID` matches your Directory (tenant) ID
4. Ensure no extra spaces in your `.env` file

For a device-code disk, run `php artisan onedrive:connect {disk}` again if Microsoft access was revoked, the refresh token expired, or Laravel's `APP_KEY` changed.

### Drive Not Found

**Error:** "itemNotFound" or "Resource not found"

**Solutions:**
1. Verify `SHAREPOINT_DRIVE_ID` is correct
2. For application-only OneDrive, verify `ONEDRIVE_DRIVE_ID` is configured
3. Omit `drive_id` only when using device-code authentication, which uses `/me/drive`
4. Ensure the app has access to the specified drive
5. Check the drive exists and hasn't been deleted

### Timeout Issues

**Error:** Timeouts when uploading large files

**Solutions:**
- The package automatically sets a 5-minute timeout for file operations
- For files > 250MB, consider using Microsoft's resumable upload API
- Check your PHP `max_execution_time` and `memory_limit` settings

### Clear Token Cache

If you're experiencing authentication issues:

```bash
php artisan cache:clear
```

Or clear specific SharePoint tokens:

```bash
php artisan cache:forget sharepoint_access_token_*
```

## 🧪 Testing Connection

Test your SharePoint connection:

```php
use Illuminate\Support\Facades\Storage;

Route::get('/test-sharepoint', function () {
    try {
        // Create a test file
        $testContent = 'Test file created at ' . now();
        Storage::disk('sharepoint')->put('test.txt', $testContent);
        
        // Verify it exists
        if (!Storage::disk('sharepoint')->exists('test.txt')) {
            return 'File creation failed!';
        }
        
        // Read it back
        $content = Storage::disk('sharepoint')->get('test.txt');
        
        // Clean up
        Storage::disk('sharepoint')->delete('test.txt');
        
        return 'SharePoint connection successful! Content: ' . $content;
        
    } catch (\Exception $e) {
        return 'Connection failed: ' . $e->getMessage();
    }
});
```

## 🔐 Security Best Practices

1. **Never commit credentials** - Keep `.env` in `.gitignore`
2. **Use environment-specific apps** - Separate Azure apps for dev/staging/production
3. **Rotate secrets regularly** - Set expiration dates on client secrets in Azure
4. **Monitor access logs** - Review app activity in Azure Portal regularly
5. **Principle of least privilege** - Only grant necessary permissions
6. **Secure your `.env`** - Restrict file permissions: `chmod 600 .env`
7. **Protect `APP_KEY`** - Delegated refresh tokens are encrypted with the Laravel application key

## 📚 API Reference

### Supported Flysystem Operations

| Method | Supported | Notes |
|--------|-----------|-------|
| `write()` | ✅ | Write file contents |
| `writeStream()` | ✅ | Write from stream (memory efficient) |
| `read()` | ✅ | Read file contents |
| `readStream()` | ✅ | Read as stream |
| `delete()` | ✅ | Delete file |
| `deleteDirectory()` | ✅ | Delete directory and contents |
| `createDirectory()` | ✅ | Create directory |
| `fileExists()` | ✅ | Check if file exists |
| `directoryExists()` | ✅ | Check if directory exists |
| `listContents()` | ✅ | List directory contents with Graph pagination |
| `move()` | ✅ | Move/rename file after monitored copy completion |
| `copy()` | ✅ | Copy file with Graph monitor polling |
| `lastModified()` | ✅ | Get last modified timestamp |
| `fileSize()` | ✅ | Get file size |
| `mimeType()` | ✅ | Get MIME type |
| `visibility()` | ❌ | Not supported by SharePoint/OneDrive |
| `setVisibility()` | ❌ | Not supported by SharePoint/OneDrive |

## 🤝 Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

### Development Setup

```bash
# Clone the repository
git clone https://github.com/sahablibya/laravel-sharepoint-filesystem.git
cd laravel-sharepoint-filesystem

# Install dependencies
composer install

# Run tests
composer test
```

## 📝 Changelog

Please see [CHANGELOG.md](CHANGELOG.md) for recent changes.

## 📄 License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## 💡 Credits

- Developed by [SahabLibya Development Team](https://github.com/sahablibya)
- Built on [Flysystem](https://flysystem.thephpleague.com/) by Frank de Jonge
- Powered by [Microsoft Graph API](https://docs.microsoft.com/en-us/graph/)

## 🙏 Acknowledgments

Special thanks to:
- The Laravel community for inspiration and best practices
- Microsoft for the comprehensive Graph API
- All contributors who help improve this package

## 📞 Support

- **Issues**: [GitHub Issues](https://github.com/sahablibya/laravel-sharepoint-filesystem/issues)
- **Discussions**: [GitHub Discussions](https://github.com/sahablibya/laravel-sharepoint-filesystem/discussions)
- **Email**: dev@sahablibya.ly

---

**Made with ❤️ by SahabLibya Development Team**

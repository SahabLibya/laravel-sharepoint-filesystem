# Laravel SharePoint/OneDrive Filesystem Driver

[![Latest Version on Packagist](https://img.shields.io/packagist/v/sahablibya/laravel-sharepoint-filesystem.svg?style=flat-square)](https://packagist.org/packages/sahablibya/laravel-sharepoint-filesystem)
[![Total Downloads](https://img.shields.io/packagist/dt/sahablibya/laravel-sharepoint-filesystem.svg?style=flat-square)](https://packagist.org/packages/sahablibya/laravel-sharepoint-filesystem)
[![License](https://img.shields.io/packagist/l/sahablibya/laravel-sharepoint-filesystem.svg?style=flat-square)](https://packagist.org/packages/sahablibya/laravel-sharepoint-filesystem)

A Laravel filesystem driver for SharePoint and OneDrive using Microsoft Graph API. It supports client credentials for unattended SharePoint and OneDrive for Business access, plus device-code sign-in for personal and business OneDrive accounts.

## ✨ Features

- ✅ **Application-only OAuth** - Automatic token management with client credentials flow
- ✅ **SharePoint Document Libraries** - Direct access to your SharePoint sites
- ✅ **Folder Virtual Roots** - Mount a Microsoft Graph driveItem folder as the disk root
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
3. Choose the permission model that matches your deployment:
   - For folder-scoped access, add `Files.SelectedOperations.Selected`
   - For broad access, `Files.ReadWrite.All` or `Sites.ReadWrite.All` can be used
4. Click **Grant admin consent** (requires admin privileges)
5. If using `Files.SelectedOperations.Selected`, explicitly assign the application the `write` role on the target driveItem

See [Folder-scoped application permissions](#folder-scoped-application-permissions) for the complete selected-permissions setup.

### Step 4: Get SharePoint Drive ID and Optional Folder Item ID

To use a specific SharePoint document library, you need the drive ID:

#### Using Microsoft Graph Explorer

1. Go to [Graph Explorer](https://developer.microsoft.com/en-us/graph/graph-explorer)
2. Sign in with your account
3. Find your site: `GET https://graph.microsoft.com/v1.0/sites?search=YourSiteName`
4. Get drives for that site: `GET https://graph.microsoft.com/v1.0/sites/{site-id}/drives`
5. Copy the `id` of your desired document library

To mount one folder instead of the whole drive, also retrieve that folder's Microsoft Graph driveItem `id`. Configure this value as `root_item_id`; in this package's configuration it is the **Parent Folder Item ID** returned by Microsoft Graph.

For example, resolve a folder by path and copy the `id` from the response:

```http
GET https://graph.microsoft.com/v1.0/drives/{drive-id}/root:/Backups
```

### Step 5: Environment Configuration

Add these variables to your `.env` file:

```env
GRAPH_CLIENT_ID=your-application-client-id
GRAPH_CLIENT_SECRET=your-client-secret-value
GRAPH_TENANT_ID=your-tenant-id

# Required: Specify the SharePoint document library
SHAREPOINT_DRIVE_ID=your-drive-id

# Optional: Mount this Parent Folder Item ID as the virtual disk root
SHAREPOINT_ROOT_ITEM_ID=your-folder-drive-item-id

# Optional: Prefix path beneath the mounted root (or drive root)
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
        'root_item_id' => env('SHAREPOINT_ROOT_ITEM_ID'), // Optional Parent Folder Item ID
        'prefix' => env('SHAREPOINT_PREFIX', ''), // Optional
        'copy_monitor_timeout' => env('SHAREPOINT_COPY_MONITOR_TIMEOUT', 300),
        'copy_monitor_interval_ms' => env('SHAREPOINT_COPY_MONITOR_INTERVAL_MS', 1000),
        'throw' => true, // Surface failures during setup
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
ONEDRIVE_ROOT_ITEM_ID=your-folder-drive-item-id
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
    'root_item_id' => env('ONEDRIVE_ROOT_ITEM_ID'),
    'prefix' => env('ONEDRIVE_PREFIX', 'backups'),
    'throw' => true,
],
```

Connect the account once:

```bash
php artisan onedrive:connect
```

The command displays a Microsoft URL and code. After sign-in, scheduled backups can refresh their access token without user interaction. Tokens are encrypted under `storage/app/onedrive-tokens` by default. Set a unique `token_key` for each OneDrive account when configuring multiple disks.

## Mount a Folder as the Disk Root

Set `root_item_id` to a folder's Microsoft Graph driveItem ID to mount that folder as the virtual root of the Flysystem disk:

```php
'sharepoint' => [
    'driver' => 'sharepoint',
    'auth_mode' => 'client_credentials',
    'client_id' => env('GRAPH_CLIENT_ID'),
    'client_secret' => env('GRAPH_CLIENT_SECRET'),
    'tenant_id' => env('GRAPH_TENANT_ID'),
    'drive_id' => env('SHAREPOINT_DRIVE_ID'),
    'root_item_id' => env('SHAREPOINT_ROOT_ITEM_ID'),
    'prefix' => env('SHAREPOINT_PREFIX', ''),
    'throw' => true,
],
```

```env
SHAREPOINT_DRIVE_ID=b!...
SHAREPOINT_ROOT_ITEM_ID=01...
SHAREPOINT_PREFIX=
```

`root_item_id` is a folder's Microsoft Graph driveItem `id`, sometimes called the **Parent Folder Item ID**. It is not the folder's name, a sharing URL, or a browser URL. Retrieve the folder through Graph and use its `id` from the response. Logical disk paths are resolved below that item. A configured `prefix` is then applied inside the item root; it is not a physical path above the item.

For example:

```php
Storage::disk('sharepoint')->put(
    'fssi-backup/example.zip',
    'backup contents',
);
```

uses:

```http
PUT /drives/{driveId}/items/{rootItemId}:/fssi-backup/example.zip:/content
```

This is Microsoft Graph's [upload by parent item ID](https://learn.microsoft.com/en-us/graph/api/driveitem-put-content?view=graph-rest-1.0) request form.

With delegated authentication and no `drive_id`, the equivalent path starts with `/me/drive/items/{rootItemId}`. If `root_item_id` is omitted, the package retains its existing `/drives/{driveId}/root` or `/me/drive/root` routing.

An empty logical path addresses the mounted item itself (or the configured prefix beneath it). For safety, the adapter refuses to delete or move a configured item root through an empty logical path.

### Destination path construction

For Spatie Laravel Backup, the remote destination is:

```text
Mounted root / disk prefix / Spatie backup name / filename
```

Empty components are omitted. The filename is the Spatie filename prefix followed by its timestamp and `.zip`, unless you explicitly customize the filename.

| Setting | Meaning | Example |
| --- | --- | --- |
| Disk name | The key under `filesystems.disks`; used by `Storage::disk()` and Spatie's destination disks. It does not create a remote folder. | `onedrive` |
| `driver` | The registered adapter; `sharepoint` and `onedrive` are aliases for the same implementation. Authentication is chosen by `auth_mode`. | `sharepoint` |
| `drive_id` | Selects the Graph drive. App-only access needs it; delegated access can use the signed-in user's drive. | Graph drive ID |
| `root_item_id` | Mounts an existing Graph folder by ID. Omit it to use the drive root. | Graph folder ID |
| Disk `prefix` | A relative folder path inside the mounted root, applied once to every disk operation. | `''` or `schools/daily` |
| Spatie `backup.name` | The backup folder relative to the disk; accessed as `config('backup.backup.name')`. | `imamMalik_schools_system_backup` |
| Spatie `backup.destination.filename_prefix` | Text prepended to the ZIP basename, not a folder. Keep it free of path separators. | `imamMalik_schools_system_backup_` |

For a mounted folder displayed in OneDrive as `Shared Backups`:

```text
# Single folder: prefix="", backup.name=imamMalik_schools_system_backup
Shared Backups/imamMalik_schools_system_backup/imamMalik_schools_system_backup_2026-09-05-15-00-34.zip

# Nested folders: prefix="schools/daily", same backup name and filename prefix
Shared Backups/schools/daily/imamMalik_schools_system_backup/imamMalik_schools_system_backup_2026-09-05-15-00-34.zip
```

Do not repeat the mounted folder's name in `prefix`, or repeat `backup.name` in `prefix` unless you want two nested folders with that name. Pass ordinary, unencoded relative paths; the adapter encodes each Graph path segment, including spaces, `%`, `#`, Unicode, and Arabic names. Encoding does not override Microsoft's [filename restrictions](https://learn.microsoft.com/en-us/graph/onedrive-addressing-driveitems#path-encoding).

The `SHAREPOINT_*` and `ONEDRIVE_*` environment names only matter where your disk configuration reads them. An `onedrive` disk can read `SHAREPOINT_PREFIX`; renaming the disk does not change that mapping. See the [complete backup recipe](INSTALLATION.md#using-with-spatie-laravel-backup).

### Folder-scoped application permissions

`root_item_id` changes Microsoft Graph routing only; it does not grant the application access to the folder.

For app-only folder-scoped access with client credentials, the Microsoft Entra application needs all of the following:

1. Microsoft Graph `Files.SelectedOperations.Selected` as an **Application** permission.
2. Administrator consent for that application permission.
3. An explicit `write` permission assignment for the application on the target driveItem.

Create the resource assignment using an appropriately authorized administrator process:

```http
POST /drives/{driveId}/items/{rootItemId}/permissions
Content-Type: application/json

{
    "grantedToV2": {
        "application": {
            "id": "{applicationClientId}"
        }
    },
    "roles": ["write"]
}
```

Selected permissions grant no access until the resource assignment is created. See Microsoft's [Selected permissions overview](https://learn.microsoft.com/en-us/graph/permissions-selected-overview) and [create permission on a driveItem](https://learn.microsoft.com/en-us/graph/api/driveitem-post-permissions?view=graph-rest-1.0) documentation.

A signed-in user's personal permission on the folder does not transfer to a client-credentials token, because app-only access is evaluated for the application rather than a user. Broader application permissions such as `Files.ReadWrite.All` or `Sites.ReadWrite.All` can also work, but they are unnecessary when folder-scoped selected permissions and the explicit driveItem assignment are configured correctly.

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

Follow the [complete installation and backup recipe](INSTALLATION.md#using-with-spatie-laravel-backup) for credentials, disk registration, configuration refresh, commands, and the resulting path. The relevant structure in the published `config/backup.php` is:

```php
return [
    'backup' => [
        'name' => env('BACKUP_NAME', 'imamMalik_schools_system_backup'),
        // Keep your published source and other backup settings here.
        'destination' => [
            'filename_prefix' => env('BACKUP_FILENAME_PREFIX', 'imamMalik_schools_system_backup_'),
            'disks' => ['onedrive'],
            // Keep your published compression and other destination settings.
        ],
    ],
    'monitor_backups' => [
        [
            'name' => env('BACKUP_NAME', 'imamMalik_schools_system_backup'),
            'disks' => ['onedrive'],
            'health_checks' => [
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays::class => 1,
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes::class => 5000,
            ],
        ],
    ],
    // Keep your published notifications and cleanup settings.
];
```

Merge these settings into the published configuration; this excerpt is not a replacement for the whole file. `destination` belongs inside `backup`; `monitor_backups` is a top-level sibling of `backup`. Laravel's full lookup keys are `backup.backup.name`, `backup.backup.destination.disks`, `backup.backup.destination.filename_prefix`, and `backup.monitor_backups`.

With `SHAREPOINT_PREFIX=""` mapped to the disk's `prefix`, the example produces this path beneath the mounted root:

```text
imamMalik_schools_system_backup/
  imamMalik_schools_system_backup_2026-09-05-15-00-34.zip
```

The timestamp illustrates Spatie's default naming at that application-local time. See [multiple applications sharing one root](INSTALLATION.md#multiple-applications-sharing-one-root) for separate project folders and monitoring.

Spatie's [destination writer](https://github.com/spatie/laravel-backup/blob/9.3.6/src/BackupDestination/BackupDestination.php) uploads the local archive through Flysystem `writeStream()`. Its “Copying zip” console message does not mean it invoked this adapter's Graph `copy()` operation.

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
    'root_item_id' => env('ONEDRIVE_ROOT_ITEM_ID'),
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

### Missing or misplaced backups

1. Confirm the terminal, cron entry, or scheduler runs from the intended Laravel project directory. Run `pwd` and check the path to `artisan`; two applications can have identically named disks but different resolved configuration.
2. Inspect the resolved values below, not just `.env`. `config/backup.php` must have `destination` inside `backup`, and the monitoring name must match `backup.name` exactly. Check `--only-to-disk` or a custom Spatie `--config` argument if your job uses one.
3. Refresh configuration in that project: use `php artisan config:clear` during local setup, or `php artisan config:cache` to rebuild a deployment's cached configuration. Open a new console session and restart long-running workers that hold the old disk instance. `cache:clear` is not a substitute for refreshing configuration. Laravel does not load `.env` when configuration is cached; read `env()` in configuration files, then use `config()` in application code. See [Laravel configuration caching](https://laravel.com/docs/configuration#configuration-caching).
4. Reconstruct `mounted root / prefix / backup name / filename`. Check for a repeated backup name in `prefix`, an unexpected `APP_NAME` fallback, or an environment variable that the disk does not actually read. Changing configuration leaves existing archives at their old paths.
5. Check the backup command's exit status and the failing operation. An empty `backup:list` result or a folder visible in OneDrive does not prove that the ZIP uploaded successfully.

For a credential-free configuration summary, start `php artisan tinker` in the intended application (if Laravel Tinker is installed) and paste this whole expression. It prints only the listed fields, without resolving the disk or requesting a token:

```php
(function (): void {
    $diskName = 'onedrive'; // Change to your configured disk name.
    $diskConfig = config("filesystems.disks.{$diskName}", []);

    dump([
        'project' => base_path(),
        'environment' => app()->environment(),
        'configuration_cached' => app()->configurationIsCached(),
        'disk_name' => $diskName,
        'disk' => \Illuminate\Support\Arr::only($diskConfig, [
            'driver', 'auth_mode', 'drive_id', 'root_item_id', 'prefix', 'throw',
        ]),
        'backup_name' => config('backup.backup.name'),
        'destination_disks' => config('backup.backup.destination.disks'),
        'filename_prefix' => config('backup.backup.destination.filename_prefix'),
        'monitor_backups' => array_map(
            static fn (array $monitor): array => \Illuminate\Support\Arr::only($monitor, ['name', 'disks']),
            config('backup.monitor_backups', []),
        ),
    ]);
})();
```

Do not dump the complete filesystem configuration, `.env`, disk object, or token store. Those can contain client secrets or tokens. Review folder and drive identifiers before sharing the allowlisted summary publicly.

### Visible exceptions during setup

Set `'throw' => true` on the disk in `config/filesystems.php`, then refresh configuration. Laravel's filesystem wrapper will rethrow supported failures such as `UnableToWriteFile`, `UnableToCreateDirectory`, `UnableToCopyFile`, and `UnableToMoveFile`, instead of returning `false`. If you choose `throw => false`, check those return values explicitly. This setting does not enable application debug mode or change permissions. See [Laravel failed writes](https://laravel.com/docs/filesystem#failed-writes).

There is a package limitation in v1.4.0 and the current adapter: `fileExists()` and `directoryExists()` catch errors and return `false`; `listContents()` can stop silently on an HTTP error or exception, returning an empty or partial listing. `throw => true` cannot restore an exception the adapter already swallowed. A missing-looking file or empty listing is therefore inconclusive. Use a read of a known existing harmless file to check reading, and the explicit console write test below to check uploading.

### Identify the failing operation

| Stage | What to investigate |
| --- | --- |
| Authentication | Disk resolution obtains or refreshes a token. Check authentication mode, app registration, secret expiry for client credentials, or the delegated connection. Do not print tokens. |
| Listing / metadata | Check the drive, mounted folder ID, prefix, and access to that location. A successful listing only demonstrates listing access; empty results can hide errors as described above. |
| Directory creation | `makeDirectory()` posts a new child under an existing parent. It does not recursively create parents and requests Graph conflict behavior `rename`. Folder creation success does not demonstrate file upload success. |
| Upload | `put()` / `writeStream()` use Graph `PUT ...:/content`. Check the full destination, existing parent folders, archive size, HTTP status, and Graph error. A successful listing or directory creation does not prove uploading works. |
| Server-side copy | `copy()` first resolves destination-parent metadata, posts `...:/copy`, then polls a monitor URL. Capture which stage failed and the previous exception. A successful upload or read does not test this operation. |
| Move | This adapter calls `copy()` and only then deletes the source. A failed copy leaves the source in place; a timeout can leave an unconfirmed destination, so inspect before retrying. |

Spatie uploads its local ZIP through `writeStream()` even when its console output calls the step “copying”. Diagnose an upload exception separately from an explicit `Storage::disk(...)->copy()` failure.

### Copy failure investigation

During integration of v1.4.0, OneDrive folder creation, upload, read, and delete succeeded, while `copy()` raised `UnableToCopyFile`. Transferring the same file with read/write succeeded, and its contents were verified before deleting the source. The underlying Graph response was not captured, so **the cause of that copy failure remains unknown**. These observations do not establish a permission limitation for OneDrive or selected permissions.

Inspection of [the adapter](src/SharePointAdapter.php) and [its tests](tests/Unit/SharePointAdapterTest.php) shows:

- Copy resolves the destination parent under the mounted root and prefix, and submits its `driveId` and `id` with the new name. This matches the parent reference documented by [Microsoft Graph copy](https://learn.microsoft.com/en-us/graph/api/driveitem-copy?view=graph-rest-1.0). The destination parent must already exist.
- A `202` response alone is insufficient: the adapter requires `Location` and polls until the JSON status is `completed`. Failed HTTP responses, failed jobs, a missing monitor URL/status, or expiry of the configured wait cause an exception. `move()` does not delete the source on these failures.
- `UnableToCopyFile` retains the earlier exception through `getPrevious()`. Parent lookup failures can be nested another level down. Inspect that chain in a private console or protected logs; printing only the outer message loses the useful reason. Initial HTTP failures retain the response body in an inner message; monitor-job failures retain `error.message`. The adapter does not expose a structured record of HTTP status, headers, and every Graph error field.
- Existing mocked tests cover roots, nested parents, prefix handling, mounted-root containment, monitor completion/failure, and source preservation on move failure. They do not reproduce the original cloud incident.

A separate, reproducible limitation is that a copy-monitor `429` response fails immediately: the adapter does not respect `Retry-After` or retry that request. A [focused mocked test](tests/Unit/CopyMonitoringTest.php) records this behavior and verifies that `move()` keeps the source. Microsoft's [throttling guidance](https://learn.microsoft.com/en-us/graph/throttling) calls for waiting before retrying. This is not evidence that the integration incident was throttling-related.

**Proposed runtime follow-up, not implemented here:** add bounded retries for monitor GET requests on `429`, respecting `Retry-After` within the copy-monitor deadline, with tests for eventual completion, exhaustion, and source preservation. Review selected transient `5xx` handling separately; do not blindly repeat an accepted copy POST. This documentation update does not change runtime behavior.

For a future reproduction, record the operation stage, HTTP status, Graph error `code` and `message`, request ID/date if available, package/Laravel versions, and redacted source/destination paths. Remove authorization headers, tokens, secrets, and signed monitor/download URLs before sharing. Do not broaden permissions based only on `UnableToCopyFile`.

A manual read/write transfer is a separate operation, not an automatic package fallback. If deliberately used, choose a fresh destination, preserve the source until a complete read-back comparison or checksum succeeds, and handle upload/verification failures without deleting the source. It does not promise to preserve Graph versions or metadata. The current `readStream()` also buffers the full file, so it is not a constant-memory workaround for large backups.

### Permission Errors

**Error:** "Access denied" or "403 Forbidden"

**Solutions:**
1. Verify the configured permission model: `Files.SelectedOperations.Selected`, `Files.ReadWrite.All`, or `Sites.ReadWrite.All`
2. Ensure **admin consent is granted** (look for green checkmarks in Azure Portal)
3. For selected permissions, verify the application has an explicit `write` assignment on the target driveItem
4. Verify `SHAREPOINT_ROOT_ITEM_ID` or `ONEDRIVE_ROOT_ITEM_ID` is the target folder's driveItem ID
5. Capture the failed operation and Graph error before changing permissions; folder selection and authentication mode must agree with your setup

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
- The adapter sets a 300-second HTTP timeout for `write()` and `writeStream()`; this is not a timeout guarantee for every filesystem operation
- It uses a single-request content upload, which Graph limits to 250 MB. [Upload sessions](https://learn.microsoft.com/en-us/graph/api/driveitem-createuploadsession?view=graph-rest-1.0) are needed for larger files and are not implemented by this adapter
- Check your PHP `max_execution_time` and `memory_limit` settings

### Clear Token Cache

Client-credentials tokens use a hashed `sharepoint_access_token_...` cache key and a 3,500-second cache lifetime. Laravel's `cache:forget` accepts an exact key, not a wildcard: `sharepoint_access_token_*` will not clear all matching tokens. A broad `php artisan cache:clear` affects the application's default cache, including unrelated entries; it is not needed merely because a backup path changed. Delegated tokens are stored separately in encrypted files, so clearing the application cache does not reconnect a device-code account.

## Testing connection from the console

Run this only when you intend to create and delete a temporary remote file. From the intended application directory, start `php artisan tinker` (requires Laravel Tinker), then paste the whole expression below. Use your actual disk name. The closure avoids displaying the disk object or configuration in Tinker's output.

The file is placed directly below the disk prefix, if any; this does not create the Spatie backup-name folder. Ensure the configured prefix already exists. To verify the exact backup folder, prepend its confirmed existing name to `$path`.

```php
(function (): void {
    $diskName = 'onedrive'; // Or 'sharepoint'.
    $diskConfig = config("filesystems.disks.{$diskName}");
    if (! is_array($diskConfig)) {
        throw new \RuntimeException('The selected disk is not configured.');
    }
    $disk = \Illuminate\Support\Facades\Storage::build(
        array_replace($diskConfig, ['throw' => true]),
    );
    $path = 'sharepoint-check-'.bin2hex(random_bytes(16)).'.txt';
    $expected = "SharePoint filesystem console verification.\n";

    if ($disk->exists($path)) {
        throw new \RuntimeException('Temporary name already exists; stop and generate a new name.');
    }

    echo "Temporary disk-relative path: {$path}\n";
    $writeAttempted = false;
    try {
        echo "Uploading harmless content...\n";
        $writeAttempted = true;
        if ($disk->put($path, $expected) !== true) {
            throw new \RuntimeException('Temporary upload failed.');
        }

        echo "Reading and comparing content...\n";
        if ($disk->get($path) !== $expected) {
            throw new \RuntimeException('Temporary file content did not match.');
        }
        echo "Upload and read-back verification passed.\n";
    } finally {
        if ($writeAttempted) {
            try {
                if ($disk->delete($path) !== true) {
                    throw new \RuntimeException('Temporary file deletion failed.');
                }
                echo "Temporary file deleted.\n";
            } catch (\Throwable) {
                // Preserve the original failure, if any, without dumping HTTP details.
                echo "Cleanup not confirmed; inspect only this temporary path: {$path}\n";
            }
        }
    }
})();
```

Generate a new random filename for every run; never substitute an existing backup filename. The collision check is an extra guard, not an atomic create-only guarantee: this adapter's existence checks can hide errors and its writes replace existing content. The unpredictable 128-bit suffix avoids reusing a shared test name. If interrupted or cleanup is not confirmed, inspect that exact printed temporary path before removing it. This check verifies upload, read-back contents, and deletion separately; it does not test Graph `copy()` or a full backup job.

### Proposed diagnostic command

`sharepoint:check --disk=onedrive` is a **future proposal**, not a command shipped by this package. A useful design would show an allowlisted resolved path and separate authentication/listing results by default, require an explicit `--write-test` to create a unique temporary file, verify its contents, and report cleanup independently. It should never dump secrets or start database exports. Use the console example above until such a command is separately implemented.

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
- **Email**: dev@sahablibya.ly

---

**Made with ❤️ by SahabLibya Development Team**

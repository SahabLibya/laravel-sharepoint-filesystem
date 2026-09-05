# Installation Guide

## For New Laravel Projects

### 1. Install via Composer

From the Laravel application directory (the directory containing `artisan`):

```bash
composer require sahablibya/laravel-sharepoint-filesystem
```

Or install from a local path:

In your application's `composer.json`:

```json
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
# Optional: mount an existing folder by its Graph driveItem ID
SHAREPOINT_ROOT_ITEM_ID=your-folder-drive-item-id
# Optional: subdirectories beneath that mounted root
SHAREPOINT_PREFIX=""
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
        'root_item_id' => env('SHAREPOINT_ROOT_ITEM_ID'),
        'prefix' => env('SHAREPOINT_PREFIX', ''),
        'throw' => true,
    ],
],
```

Register the disk under `filesystems.disks`. Publishing `sharepoint-filesystem.php` alone does not register its example disks with Laravel's filesystem manager.

### 4. Refresh Configuration and Verify

Use `php artisan config:clear` while configuring locally, or `php artisan config:cache` to rebuild a deployment's cached configuration. Then follow the [console verification example](README.md#testing-connection-from-the-console), setting its disk name to `sharepoint`. It uses unique harmless content, checks the read-back content, and cleans up its temporary file.

Use `throw => true` during setup so Laravel surfaces filesystem failures. Read the [troubleshooting limitations](README.md#visible-exceptions-during-setup): listing and existence checks can still hide adapter errors.

## Personal OneDrive

To connect a personal Microsoft account, register a Microsoft Entra public client that supports personal accounts, enable public client flows, and grant the delegated `Files.ReadWrite` permission.

Configure `config/filesystems.php`:

```php
'onedrive' => [
    'driver' => 'onedrive',
    'auth_mode' => 'device_code',
    'client_id' => env('ONEDRIVE_CLIENT_ID'),
    'tenant_id' => 'consumers',
    'token_key' => 'personal-backups',
    'root_item_id' => env('ONEDRIVE_ROOT_ITEM_ID'),
    'prefix' => env('ONEDRIVE_PREFIX', 'backups'),
    'throw' => true,
],
```

Set the public client ID in `.env`; the folder ID and prefix are optional:

```env
ONEDRIVE_CLIENT_ID=your-public-application-client-id
ONEDRIVE_ROOT_ITEM_ID=your-folder-drive-item-id
ONEDRIVE_PREFIX=backups
```

Refresh configuration as above, then connect the account from this application:

```bash
php artisan onedrive:connect
```

The refresh token is stored encrypted under `storage/app/onedrive-tokens`. Personal OneDrive does not require `GRAPH_CLIENT_SECRET`, `GRAPH_TENANT_ID`, or `ONEDRIVE_DRIVE_ID`.

`SHAREPOINT_ROOT_ITEM_ID` or `ONEDRIVE_ROOT_ITEM_ID` may be set to an existing folder's Graph driveItem `id` to mount that folder as the disk root. A folder name, sharing link, or browser URL is not an item ID. Resolve the folder with Graph and take its `id`; see [mounting a folder and path construction](README.md#mount-a-folder-as-the-disk-root).

Mounting changes routing, not access rights. For the selected application-permissions model, follow the [explicit folder assignment instructions](README.md#folder-scoped-application-permissions). Device-code authentication instead uses delegated permissions for the signed-in account.

## Using with Spatie Laravel Backup

### 1. Install Spatie Backup

Use a Spatie version compatible with your application's PHP and Laravel versions. These keys are verified against Spatie 9.3.6 source and the current [Spatie installation guide](https://spatie.be/docs/laravel-backup/v10/installation-and-setup). Preserve the published defaults for your installed version.

```bash
composer require spatie/laravel-backup
php artisan vendor:publish --provider="Spatie\Backup\BackupServiceProvider" --tag=backup-config
```

### 2. Configure the OneDrive Disk and Environment

This complete destination recipe uses **OneDrive for Business with client credentials** and mounts an existing folder. The Laravel disk is named `onedrive`, its driver is `sharepoint` (the `onedrive` driver alias works too), and it deliberately reads `SHAREPOINT_*` path variables.

In the application's `.env`:

```env
GRAPH_CLIENT_ID=your-application-client-id
GRAPH_CLIENT_SECRET=your-client-secret-value
GRAPH_TENANT_ID=your-tenant-id
SHAREPOINT_DRIVE_ID=your-onedrive-for-business-drive-id
SHAREPOINT_ROOT_ITEM_ID=your-mounted-folder-graph-item-id
SHAREPOINT_PREFIX=""

BACKUP_NAME=imamMalik_schools_system_backup
BACKUP_FILENAME_PREFIX=imamMalik_schools_system_backup_
```

Add this entry inside the existing `disks` array in `config/filesystems.php`:

```php
'onedrive' => [
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

For **personal OneDrive**, replace that disk entry with the following delegated configuration. Use the [public client registration steps](README.md#register-a-public-client), set `ONEDRIVE_CLIENT_ID`, and retain `SHAREPOINT_ROOT_ITEM_ID`, `SHAREPOINT_PREFIX`, `BACKUP_NAME`, and `BACKUP_FILENAME_PREFIX` from above. The `GRAPH_*` credentials and `SHAREPOINT_DRIVE_ID` are not needed in this variant:

```php
'onedrive' => [
    'driver' => 'onedrive',
    'auth_mode' => 'device_code',
    'client_id' => env('ONEDRIVE_CLIENT_ID'),
    'tenant_id' => 'consumers',
    'token_key' => 'personal-backups',
    'root_item_id' => env('SHAREPOINT_ROOT_ITEM_ID'),
    'prefix' => env('SHAREPOINT_PREFIX', ''),
    'throw' => true,
],
```

```env
ONEDRIVE_CLIENT_ID=your-public-application-client-id
```

After refreshing configuration in step 4, connect this variant once with `php artisan onedrive:connect onedrive`. Future scheduled runs use the encrypted refresh token.

### 3. Configure Backup Naming, Destination, and Monitoring

Merge this structure into the published `config/backup.php`, retaining your application's source files, database connections, compression, notifications, and cleanup settings. Do not replace the entire file with this excerpt:

```php
return [
    'backup' => [
        'name' => env('BACKUP_NAME', 'imamMalik_schools_system_backup'),

        // Keep the published source and other backup settings here.

        'destination' => [
            'filename_prefix' => env('BACKUP_FILENAME_PREFIX', 'imamMalik_schools_system_backup_'),
            'disks' => ['onedrive'],
            // Keep the published compression and other destination settings.
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

    // Keep the published notifications and cleanup settings here.
];
```

The configuration file is named `backup.php`, so `backup.name` within that file is read using `config('backup.backup.name')`. Likewise, use `config('backup.backup.destination.disks')` and `config('backup.backup.destination.filename_prefix')`. `monitor_backups` is outside the inner `backup` array and is read as `config('backup.monitor_backups')`.

The monitoring name must exactly match the backup name, and its disk must point to the same root and prefix. Adjust health checks to your schedule and retention needs. You may add `'local'` to both disk lists if you also want a local copy.

### 4. Refresh Configuration and Run a Backup

From the intended application directory:

```bash
cd /path/to/imam-malik-application
pwd

# During local setup
php artisan config:clear
```

For a deployment that uses cached configuration, rebuild it with `php artisan config:cache` after the edits instead. Restart long-running application workers that use the disk, and open a fresh Tinker session. See [Laravel configuration caching](https://laravel.com/docs/configuration#configuration-caching).

Check the [resolved configuration](README.md#missing-or-misplaced-backups) before writing. Ensure the mounted folder, any prefix folders, and the backup-name folder exist. The adapter does not recursively provision destination parents: `makeDirectory()` creates one child in an existing parent and uses Graph's `rename` conflict behavior. Do not repeatedly create a folder that already exists and assume the returned name is unchanged.

First use the [console verification example](README.md#testing-connection-from-the-console) for a tiny upload/read/delete check. When ready to create actual backups, run one of the following [Spatie backup commands](https://spatie.be/docs/laravel-backup/v9/taking-backups/overview):

```bash
# Full backup: configured files and database exports
php artisan backup:run --only-to-disk=onedrive

# Alternative: configured files only, without database exports
php artisan backup:run --only-files --only-to-disk=onedrive

# Alternative: database exports only
php artisan backup:run --only-db --only-to-disk=onedrive

# Inspect backups for the configured monitoring name and disk
php artisan backup:list
```

These backup commands create real archives and upload them. `--only-files` still backs up the configured application files; use the tiny console check for diagnostics. Review the exit status and any exception, then inspect the remote file. Spatie monitoring does not distinguish a full backup from a files-only or database-only archive.

With Spatie's default timestamp naming at application-local time `2026-09-05 15:00:34`, the result beneath the mounted root is:

```text
imamMalik_schools_system_backup/
  imamMalik_schools_system_backup_2026-09-05-15-00-34.zip
```

This follows `Mounted root / disk prefix / Spatie backup name / filename`. Here the disk prefix is empty. Setting it to `schools/daily` instead produces:

```text
schools/daily/imamMalik_schools_system_backup/
  imamMalik_schools_system_backup_2026-09-05-15-00-34.zip
```

See [destination path construction](README.md#destination-path-construction) for the distinction between the disk name, driver, mounted folder ID, folder prefix, backup name, and filename prefix.

### Multiple Applications Sharing One Root

Two applications may use the same authorized Microsoft application credentials, drive ID, and mounted root ID. Each application can independently name its Laravel disk `onedrive`. Give each a distinct backup name and matching monitoring name so each application's backup listing and retention configuration target its own folder.

Use the recipe above in both projects, with these per-project `.env` values:

| Setting | Imam Malik application | Second application |
| --- | --- | --- |
| `SHAREPOINT_PREFIX` | `""` | `""` |
| `BACKUP_NAME` | `imamMalik_schools_system_backup` | `school_portal_backup` |
| `BACKUP_FILENAME_PREFIX` | `imamMalik_schools_system_backup_` | `school_portal_backup_` |
| Destination and monitoring disk | `onedrive` | `onedrive` |

Both the backup and monitoring names read `BACKUP_NAME` in each project's configuration. The remote layout is:

```text
Mounted root/
  imamMalik_schools_system_backup/
    imamMalik_schools_system_backup_2026-09-05-15-00-34.zip
  school_portal_backup/
    school_portal_backup_2026-09-05-15-00-34.zip
```

Alternatively, give each disk a distinct prefix such as `projects/imam-malik` and `projects/school-portal`; the backup-name folder remains beneath that prefix. Distinct filename prefixes alone do not separate folders. These paths organize backups; they are not an authorization boundary between applications using the same credentials.

For delegated personal OneDrive, both applications can use the same public client registration and sign into the same account, but connect each application separately. Keep each application's encrypted token store and `APP_KEY` independent; do not copy encrypted token files between applications with different keys.

Refresh configuration in **each** project and confirm that each scheduler runs its own `artisan` from its intended project directory. Changing `root_item_id`, disk `prefix`, or `backup.name` does not move or rename existing backups. Changing `filename_prefix` only changes future filenames. Older archives stay at their previous paths and may no longer appear in the new monitoring view; plan any migration and retention review separately.

## Troubleshooting

- [Missing or misplaced backups](README.md#missing-or-misplaced-backups): check the project directory, allowlisted resolved configuration, and configuration cache.
- [Visible exceptions during setup](README.md#visible-exceptions-during-setup): understand `throw => true` and the limitations of listing and existence checks.
- [Identify the failing operation](README.md#identify-the-failing-operation): separate authentication, listing, directory creation, upload, and Graph copy failures.
- [Copy failure investigation](README.md#copy-failure-investigation): the reported integration cause is unknown; a verified read/write transfer does not establish why Graph copy failed.
- [Upload limits and timeouts](README.md#timeout-issues): writes use a 300-second HTTP timeout and a single-request upload, not resumable upload sessions.

A future `sharepoint:check --disk=onedrive` with an explicit `--write-test` option is a [proposal only](README.md#proposed-diagnostic-command); it is not an installed command.

## Features

✅ Automatic token management (no manual OAuth)  
✅ Support for SharePoint Document Libraries  
✅ Support for personal and business OneDrive
✅ Token caching (58 minutes)  
✅ Large file support (5-minute timeout)  
✅ Compatible with Laravel 10, 11, 12, 13  
✅ Works with Spatie Laravel Backup  

## Support

For issues or questions, check the main README.md file.

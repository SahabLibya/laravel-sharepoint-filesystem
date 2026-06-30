<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // getAccessToken() caches via Cache::remember(); the array store needs no DB.
    config()->set('cache.default', 'array');
});

it('resolves the filesystem driver in the correct scope for each alias', function (string $driver) {
    config()->set('filesystems.disks.sp_driver_test', [
        'driver' => $driver,
        // Credentials intentionally omitted.
    ]);

    // Resolving the disk must run the Storage::extend() closure in the right
    // scope and reach the package's own credential check. Before the Laravel 13
    // fix this fatalled with:
    //   Error: Call to undefined method Illuminate\Filesystem\FilesystemManager::getAccessToken()
    // because L13 rebinds the closure's $this to the FilesystemManager.
    Storage::disk('sp_driver_test');
})
    ->with(['sharepoint', 'onedrive'])
    ->throws(RuntimeException::class, 'SharePoint/OneDrive credentials not configured');

<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use SahabLibya\SharePointFilesystem\Authentication\DelegatedTokenStore;
use SahabLibya\SharePointFilesystem\Tests\Support\InMemoryDelegatedTokenStore;

beforeEach(function () {
    // Client-credentials tokens use Cache::remember(); the array store needs no DB.
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

it('connects a OneDrive disk through the device-code command', function () {
    $store = new InMemoryDelegatedTokenStore;
    app()->instance(DelegatedTokenStore::class, $store);

    config()->set('filesystems.disks.personal', [
        'driver' => 'onedrive',
        'auth_mode' => 'device_code',
        'client_id' => 'public-client-id',
        'tenant_id' => 'consumers',
        'token_key' => 'personal-backups',
    ]);

    Http::fakeSequence()
        ->push([
            'device_code' => 'device-code',
            'user_code' => 'ABCD-EFGH',
            'verification_uri' => 'https://microsoft.com/devicelogin',
            'expires_in' => 900,
            'interval' => 0,
            'message' => 'Open https://microsoft.com/devicelogin and enter ABCD-EFGH.',
        ])
        ->push([
            'access_token' => 'delegated-access-token',
            'refresh_token' => 'delegated-refresh-token',
            'expires_in' => 3600,
        ]);

    $this->artisan('onedrive:connect', ['disk' => 'personal'])
        ->expectsOutput('Open https://microsoft.com/devicelogin and enter ABCD-EFGH.')
        ->expectsOutput('OneDrive connected successfully for filesystem disk [personal].')
        ->assertExitCode(0);

    expect($store->get('personal-backups'))
        ->access_token->toBe('delegated-access-token')
        ->refresh_token->toBe('delegated-refresh-token');
});

it('uses a connected personal OneDrive through me drive without app credentials', function () {
    $store = new InMemoryDelegatedTokenStore;
    $store->put('personal-backups', [
        'access_token' => 'delegated-access-token',
        'refresh_token' => 'delegated-refresh-token',
        'expires_at' => time() + 3600,
    ]);
    app()->instance(DelegatedTokenStore::class, $store);

    config()->set('filesystems.disks.personal', [
        'driver' => 'onedrive',
        'auth_mode' => 'device_code',
        'client_id' => 'public-client-id',
        'tenant_id' => 'consumers',
        'token_key' => 'personal-backups',
        'prefix' => 'backups',
    ]);

    Http::fake([
        'graph.microsoft.com/*' => Http::response(['id' => 'uploaded-item'], 201),
    ]);

    Storage::disk('personal')->put('database.zip', 'backup contents');

    Http::assertSent(function (Request $request): bool {
        return $request->method() === 'PUT'
            && $request->url() === 'https://graph.microsoft.com/v1.0/me/drive/root:/backups/database.zip:/content'
            && $request->hasHeader('Authorization', 'Bearer delegated-access-token');
    });
});

it('wires the default encrypted delegated token store into Laravel', function () {
    $directory = sys_get_temp_dir().'/onedrive-binding-test-'.bin2hex(random_bytes(6));
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    config()->set('sharepoint-filesystem.token_storage_path', $directory);

    try {
        $store = app(DelegatedTokenStore::class);
        $store->put('personal', [
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'expires_at' => time() + 3600,
        ]);

        expect($store->get('personal')['refresh_token'])->toBe('refresh-token');
    } finally {
        (new Filesystem)->deleteDirectory($directory);
    }
});

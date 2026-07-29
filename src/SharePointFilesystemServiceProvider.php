<?php

declare(strict_types=1);

namespace SahabLibya\SharePointFilesystem;

use Closure;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Filesystem\Filesystem as IlluminateFilesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use SahabLibya\SharePointFilesystem\Authentication\DelegatedTokenStore;
use SahabLibya\SharePointFilesystem\Authentication\EncryptedFileTokenStore;
use SahabLibya\SharePointFilesystem\Console\ConnectOneDriveCommand;
use SahabLibya\SharePointFilesystem\Graph\GraphTokenProvider;

class SharePointFilesystemServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/sharepoint-filesystem.php',
            'sharepoint-filesystem'
        );

        $this->app->singleton(DelegatedTokenStore::class, function ($app): DelegatedTokenStore {
            $configuredPath = $app['config']->get('sharepoint-filesystem.token_storage_path');
            $path = is_string($configuredPath) && $configuredPath !== ''
                ? $configuredPath
                : $app->storagePath('app/onedrive-tokens');

            return new EncryptedFileTokenStore(
                $app->make(IlluminateFilesystem::class),
                $app->make(Encrypter::class),
                $path,
            );
        });

        $this->app->singleton(GraphTokenProvider::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/sharepoint-filesystem.php' => config_path('sharepoint-filesystem.php'),
        ], 'sharepoint-config');

        // Laravel 13 rebinds anonymous Storage::extend() callbacks to the
        // FilesystemManager, so keep this callable targeted at the provider.
        $createDriver = Closure::fromCallable([$this, 'createDriver']);

        Storage::extend('sharepoint', $createDriver);

        // Also register as 'onedrive' for backward compatibility
        Storage::extend('onedrive', $createDriver);

        if ($this->app->runningInConsole()) {
            $this->commands([ConnectOneDriveCommand::class]);
        }
    }

    /**
     * Create the SharePoint-backed Flysystem adapter.
     */
    private function createDriver($app, array $config): FilesystemAdapter
    {
        $accessToken = $this->resolveAccessToken($config);

        $adapter = new SharePointAdapter(
            $accessToken,
            $config['drive_id'] ?? null,
            $config['prefix'] ?? '',
            $this->adapterOptions($config)
        );

        return new FilesystemAdapter(
            new Filesystem($adapter, $config),
            $adapter,
            $config
        );
    }

    /**
     * Resolve an access token without changing the existing default flow.
     */
    private function resolveAccessToken(array $config): string
    {
        return $this->app->make(GraphTokenProvider::class)->tokenFor($config);
    }

    /**
     * Build adapter options from disk configuration.
     */
    private function adapterOptions(array $config): array
    {
        return [
            'copy_monitor_timeout' => $config['copy_monitor_timeout'] ?? 300,
            'copy_monitor_interval_ms' => $config['copy_monitor_interval_ms'] ?? 1000,
            'root_item_id' => $config['root_item_id'] ?? null,
        ];
    }
}

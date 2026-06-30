<?php

declare(strict_types=1);

namespace SahabLibya\SharePointFilesystem;

use Closure;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;

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
    }

    /**
     * Create the SharePoint-backed Flysystem adapter.
     */
    private function createDriver($app, array $config): FilesystemAdapter
    {
        // Get access token using client credentials flow
        $accessToken = $this->getAccessToken($config);

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
     * Get access token using client credentials flow with caching
     */
    private function getAccessToken(array $config): string
    {
        $cacheKey = 'sharepoint_access_token_'.md5(json_encode([
            $config['client_id'] ?? '',
            $config['tenant_id'] ?? 'common',
            $config['drive_id'] ?? '',
        ]));

        // Cache for 58 minutes (tokens usually expire in 60 minutes)
        return Cache::remember($cacheKey, 3500, function () use ($config) {
            $clientId = $config['client_id'] ?? null;
            $clientSecret = $config['client_secret'] ?? null;
            $tenantId = $config['tenant_id'] ?? 'common';

            if (! $clientId || ! $clientSecret) {
                throw new \RuntimeException(
                    'SharePoint/OneDrive credentials not configured. '.
                    'Set GRAPH_CLIENT_ID and GRAPH_CLIENT_SECRET in your .env file.'
                );
            }

            $response = Http::asForm()->post(
                "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token",
                [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'scope' => 'https://graph.microsoft.com/.default',
                    'grant_type' => 'client_credentials',
                ]
            );

            if ($response->failed()) {
                throw new \RuntimeException('Failed to obtain SharePoint access token: '.$response->body());
            }

            $data = $response->json();

            return $data['access_token'];
        });
    }

    /**
     * Build adapter options from disk configuration.
     */
    private function adapterOptions(array $config): array
    {
        return [
            'copy_monitor_timeout' => $config['copy_monitor_timeout'] ?? 300,
            'copy_monitor_interval_ms' => $config['copy_monitor_interval_ms'] ?? 1000,
        ];
    }
}

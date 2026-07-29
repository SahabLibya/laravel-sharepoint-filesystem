<?php

declare(strict_types=1);

namespace SahabLibya\SharePointFilesystem\Graph;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use SahabLibya\SharePointFilesystem\Authentication\DeviceCodeAccessTokenProvider;

final class GraphTokenProvider implements AccessTokenProvider
{
    public function __construct(
        private Container $container,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public function tokenFor(array $config): string
    {
        $authMode = strtolower((string) ($config['auth_mode'] ?? 'client_credentials'));

        return match ($authMode) {
            'client_credentials' => $this->clientCredentialsToken($config),
            'device_code' => $this->container
                ->make(DeviceCodeAccessTokenProvider::class)
                ->getAccessToken($config),
            default => throw new RuntimeException(
                "Unsupported SharePoint/OneDrive auth_mode [{$authMode}]."
            ),
        };
    }

    public function tokenForDisk(string $disk): string
    {
        $config = config("filesystems.disks.{$disk}");

        if (! is_array($config)) {
            throw new RuntimeException("Filesystem disk [{$disk}] is not configured.");
        }

        return $this->tokenFor($config);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function clientCredentialsToken(array $config): string
    {
        $cacheKey = 'sharepoint_access_token_'.md5(json_encode([
            $config['client_id'] ?? '',
            $config['tenant_id'] ?? 'common',
            $config['drive_id'] ?? '',
        ]));

        return Cache::remember($cacheKey, 3500, function () use ($config) {
            $clientId = $config['client_id'] ?? null;
            $clientSecret = $config['client_secret'] ?? null;
            $tenantId = $config['tenant_id'] ?? 'common';

            if (! $clientId || ! $clientSecret) {
                throw new RuntimeException(
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
                throw new RuntimeException('Failed to obtain SharePoint access token: '.$response->body());
            }

            $data = $response->json();

            return $data['access_token'];
        });
    }
}

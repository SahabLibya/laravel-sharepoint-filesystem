<?php

declare(strict_types=1);

namespace SahabLibya\SharePointFilesystem\Authentication;

use RuntimeException;

final class MicrosoftIdentityConfiguration
{
    public static function clientId(array $config): string
    {
        $clientId = trim((string) ($config['client_id'] ?? ''));

        if ($clientId === '') {
            throw new RuntimeException(
                'OneDrive device-code authentication requires a client_id. Set ONEDRIVE_CLIENT_ID in your .env file.'
            );
        }

        return $clientId;
    }

    public static function tenantId(array $config): string
    {
        $tenantId = trim((string) ($config['tenant_id'] ?? 'common'));

        if ($tenantId === '' || preg_match('/\A[A-Za-z0-9][A-Za-z0-9.-]*\z/', $tenantId) !== 1) {
            throw new RuntimeException('The configured Microsoft tenant_id is invalid.');
        }

        return $tenantId;
    }

    public static function scopes(array $config): array
    {
        $configured = $config['scopes'] ?? [
            'offline_access',
            'https://graph.microsoft.com/Files.ReadWrite',
        ];

        $scopes = is_string($configured)
            ? preg_split('/\s+/', trim($configured))
            : $configured;

        if (! is_array($scopes)) {
            throw new RuntimeException('The configured OneDrive scopes must be an array or space-separated string.');
        }

        $scopes = array_values(array_unique(array_filter(
            array_map(static fn ($scope): string => trim((string) $scope), $scopes),
            static fn (string $scope): bool => $scope !== '',
        )));

        if (! in_array('offline_access', $scopes, true)) {
            array_unshift($scopes, 'offline_access');
        }

        if (count($scopes) === 1) {
            throw new RuntimeException(
                'OneDrive device-code authentication requires at least one Microsoft Graph scope.'
            );
        }

        return $scopes;
    }

    public static function tokenKey(array $config): string
    {
        $key = trim((string) ($config['token_key'] ?? 'default'));

        if ($key === '') {
            throw new RuntimeException('The configured OneDrive token_key cannot be empty.');
        }

        return $key;
    }

    public static function endpoint(array $config, string $endpoint): string
    {
        return sprintf(
            'https://login.microsoftonline.com/%s/oauth2/v2.0/%s',
            self::tenantId($config),
            ltrim($endpoint, '/'),
        );
    }
}

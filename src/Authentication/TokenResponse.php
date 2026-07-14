<?php

declare(strict_types=1);

namespace SahabLibya\SharePointFilesystem\Authentication;

use RuntimeException;

final class TokenResponse
{
    public static function normalize(array $payload, ?string $fallbackRefreshToken = null): array
    {
        $accessToken = $payload['access_token'] ?? null;
        $refreshToken = $payload['refresh_token'] ?? $fallbackRefreshToken;

        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('Microsoft did not return an access token.');
        }

        if (! is_string($refreshToken) || $refreshToken === '') {
            throw new RuntimeException(
                'Microsoft did not return a refresh token. Ensure offline_access is requested and reconnect OneDrive.'
            );
        }

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at' => time() + max(1, (int) ($payload['expires_in'] ?? 3600)),
            'token_type' => (string) ($payload['token_type'] ?? 'Bearer'),
            'scope' => (string) ($payload['scope'] ?? ''),
        ];
    }
}

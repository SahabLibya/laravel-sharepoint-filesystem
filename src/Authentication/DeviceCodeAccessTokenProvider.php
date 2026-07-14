<?php

declare(strict_types=1);

namespace SahabLibya\SharePointFilesystem\Authentication;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class DeviceCodeAccessTokenProvider
{
    public function __construct(private DelegatedTokenStore $tokens) {}

    public function getAccessToken(array $config): string
    {
        $key = MicrosoftIdentityConfiguration::tokenKey($config);
        $stored = $this->tokens->get($key);

        if ($stored === null) {
            throw new RuntimeException(
                'OneDrive is not connected. Run php artisan onedrive:connect before using this disk.'
            );
        }

        $accessToken = $stored['access_token'] ?? null;
        $expiresAt = (int) ($stored['expires_at'] ?? 0);

        if (is_string($accessToken) && $accessToken !== '' && $expiresAt > time() + 60) {
            return $accessToken;
        }

        return $this->refreshAccessToken($config, $key, $stored);
    }

    private function refreshAccessToken(array $config, string $key, array $stored): string
    {
        $refreshToken = $stored['refresh_token'] ?? null;

        if (! is_string($refreshToken) || $refreshToken === '') {
            throw new RuntimeException(
                'The OneDrive connection has no refresh token. Run php artisan onedrive:connect again.'
            );
        }

        $response = Http::asForm()->post(
            MicrosoftIdentityConfiguration::endpoint($config, 'token'),
            [
                'client_id' => MicrosoftIdentityConfiguration::clientId($config),
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'scope' => implode(' ', MicrosoftIdentityConfiguration::scopes($config)),
            ],
        );

        if ($response->failed()) {
            throw new RuntimeException(
                'Unable to refresh the OneDrive connection: '.$this->errorMessage($response)
                .' Run php artisan onedrive:connect again.'
            );
        }

        $tokens = TokenResponse::normalize((array) $response->json(), $refreshToken);
        $this->tokens->put($key, $tokens);

        return $tokens['access_token'];
    }

    private function errorMessage(Response $response): string
    {
        $description = $response->json('error_description');

        if (is_string($description) && $description !== '') {
            return $description;
        }

        $error = $response->json('error');

        if (is_string($error) && $error !== '') {
            return $error;
        }

        return 'Microsoft identity returned HTTP '.$response->status().'.';
    }
}

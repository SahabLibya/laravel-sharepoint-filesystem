<?php

declare(strict_types=1);

namespace SahabLibya\SharePointFilesystem\Authentication;

use Closure;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class DeviceCodeAuthenticator
{
    public function __construct(private ?Closure $sleep = null) {}

    public function requestAuthorization(array $config): array
    {
        $response = Http::asForm()->post(
            MicrosoftIdentityConfiguration::endpoint($config, 'devicecode'),
            [
                'client_id' => MicrosoftIdentityConfiguration::clientId($config),
                'scope' => implode(' ', MicrosoftIdentityConfiguration::scopes($config)),
            ],
        );

        if ($response->failed()) {
            throw new RuntimeException('Unable to start OneDrive authorization: '.$this->errorMessage($response));
        }

        $authorization = $response->json();

        foreach (['device_code', 'user_code', 'verification_uri', 'expires_in'] as $field) {
            if (! isset($authorization[$field]) || $authorization[$field] === '') {
                throw new RuntimeException("Microsoft's device authorization response is missing {$field}.");
            }
        }

        return $authorization;
    }

    public function waitForToken(array $config, array $authorization): array
    {
        $deadline = time() + max(1, (int) $authorization['expires_in']);
        $interval = max(0, (int) ($authorization['interval'] ?? 5));

        while (time() < $deadline) {
            $response = Http::asForm()->post(
                MicrosoftIdentityConfiguration::endpoint($config, 'token'),
                [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:device_code',
                    'client_id' => MicrosoftIdentityConfiguration::clientId($config),
                    'device_code' => $authorization['device_code'],
                ],
            );

            if ($response->successful()) {
                return TokenResponse::normalize((array) $response->json());
            }

            $error = $response->json('error');

            if ($error === 'authorization_pending') {
                $this->pause($interval);

                continue;
            }

            if ($error === 'slow_down') {
                $interval += 5;
                $this->pause($interval);

                continue;
            }

            throw new RuntimeException('OneDrive authorization failed: '.$this->errorMessage($response));
        }

        throw new RuntimeException('OneDrive authorization expired before sign-in was completed.');
    }

    private function pause(int $seconds): void
    {
        if ($seconds <= 0) {
            return;
        }

        if ($this->sleep !== null) {
            ($this->sleep)($seconds);

            return;
        }

        sleep($seconds);
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

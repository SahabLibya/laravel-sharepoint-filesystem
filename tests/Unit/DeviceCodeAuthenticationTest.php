<?php

declare(strict_types=1);

use Illuminate\Encryption\Encrypter;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use SahabLibya\SharePointFilesystem\Authentication\DeviceCodeAccessTokenProvider;
use SahabLibya\SharePointFilesystem\Authentication\DeviceCodeAuthenticator;
use SahabLibya\SharePointFilesystem\Authentication\EncryptedFileTokenStore;
use SahabLibya\SharePointFilesystem\Tests\Support\InMemoryDelegatedTokenStore;

it('completes device-code authorization after Microsoft reports a pending login', function () {
    Http::fakeSequence()
        ->push([
            'device_code' => 'device-code',
            'user_code' => 'ABCD-EFGH',
            'verification_uri' => 'https://microsoft.com/devicelogin',
            'expires_in' => 900,
            'interval' => 0,
            'message' => 'Open the Microsoft login page.',
        ])
        ->push([
            'error' => 'authorization_pending',
            'error_description' => 'The user has not finished signing in.',
        ], 400)
        ->push([
            'access_token' => 'delegated-access-token',
            'refresh_token' => 'delegated-refresh-token',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
            'scope' => 'Files.ReadWrite',
        ]);

    $config = [
        'client_id' => 'public-client-id',
        'tenant_id' => 'consumers',
        'scopes' => ['offline_access', 'https://graph.microsoft.com/Files.ReadWrite'],
    ];
    $authenticator = new DeviceCodeAuthenticator;

    $authorization = $authenticator->requestAuthorization($config);
    $tokens = $authenticator->waitForToken($config, $authorization);

    expect($tokens)
        ->access_token->toBe('delegated-access-token')
        ->refresh_token->toBe('delegated-refresh-token')
        ->and($tokens['expires_at'])->toBeGreaterThan(time());

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://login.microsoftonline.com/consumers/oauth2/v2.0/devicecode'
            && $request['client_id'] === 'public-client-id'
            && $request['scope'] === 'offline_access https://graph.microsoft.com/Files.ReadWrite';
    });

    Http::assertSentCount(3);
});

it('refreshes an expired delegated access token and stores token rotation', function () {
    $store = new InMemoryDelegatedTokenStore;
    $store->put('personal', [
        'access_token' => 'expired-access-token',
        'refresh_token' => 'old-refresh-token',
        'expires_at' => time() - 60,
    ]);

    Http::fake([
        'login.microsoftonline.com/*' => Http::response([
            'access_token' => 'fresh-access-token',
            'refresh_token' => 'rotated-refresh-token',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ]),
    ]);

    $provider = new DeviceCodeAccessTokenProvider($store);
    $accessToken = $provider->getAccessToken([
        'client_id' => 'public-client-id',
        'tenant_id' => 'common',
        'token_key' => 'personal',
    ]);

    expect($accessToken)->toBe('fresh-access-token')
        ->and($store->get('personal')['refresh_token'])->toBe('rotated-refresh-token')
        ->and($store->get('personal')['expires_at'])->toBeGreaterThan(time());

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://login.microsoftonline.com/common/oauth2/v2.0/token'
            && $request['client_id'] === 'public-client-id'
            && $request['grant_type'] === 'refresh_token'
            && $request['refresh_token'] === 'old-refresh-token';
    });
});

it('uses an unexpired delegated access token without an HTTP request', function () {
    $store = new InMemoryDelegatedTokenStore;
    $store->put('default', [
        'access_token' => 'current-access-token',
        'refresh_token' => 'refresh-token',
        'expires_at' => time() + 3600,
    ]);

    Http::fake();

    $provider = new DeviceCodeAccessTokenProvider($store);

    expect($provider->getAccessToken(['client_id' => 'public-client-id']))
        ->toBe('current-access-token');

    Http::assertNothingSent();
});

it('stores delegated tokens encrypted with private file permissions', function () {
    $files = new Filesystem;
    $directory = sys_get_temp_dir().'/onedrive-token-test-'.bin2hex(random_bytes(6));
    $store = new EncryptedFileTokenStore(
        $files,
        new Encrypter(random_bytes(32), 'AES-256-CBC'),
        $directory,
    );
    $tokens = [
        'access_token' => 'secret-access-token',
        'refresh_token' => 'secret-refresh-token',
        'expires_at' => time() + 3600,
    ];

    try {
        $store->put('personal', $tokens);

        $path = $directory.'/'.hash('sha256', 'personal').'.token';
        $contents = $files->get($path);

        expect($store->get('personal'))->toBe($tokens)
            ->and($contents)->not->toContain('secret-refresh-token')
            ->and(fileperms($path) & 0777)->toBe(0600);
    } finally {
        $files->deleteDirectory($directory);
    }
});

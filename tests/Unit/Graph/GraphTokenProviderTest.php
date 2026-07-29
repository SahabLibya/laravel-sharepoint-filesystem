<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use SahabLibya\SharePointFilesystem\Authentication\DelegatedTokenStore;
use SahabLibya\SharePointFilesystem\Graph\GraphTokenProvider;
use SahabLibya\SharePointFilesystem\Tests\Support\InMemoryDelegatedTokenStore;

beforeEach(function () {
    config()->set('cache.default', 'array');
    Cache::flush();
});

it('fetches a client-credentials token and caches it to avoid a second HTTP request', function () {
    $tokenUrl = 'https://login.microsoftonline.com/test-tenant/oauth2/v2.0/token';

    Http::fake([
        $tokenUrl => Http::response([
            'access_token' => 'application-access-token',
            'expires_in' => 3600,
        ], 200),
        '*' => Http::response('unexpected request', 500),
    ]);

    $provider = app(GraphTokenProvider::class);
    $config = [
        'auth_mode' => 'client_credentials',
        'client_id' => 'test-client-id',
        'client_secret' => 'test-client-secret',
        'tenant_id' => 'test-tenant',
        'drive_id' => 'test-drive-id',
    ];

    expect($provider->tokenFor($config))->toBe('application-access-token')
        ->and($provider->tokenFor($config))->toBe('application-access-token');

    Http::assertSent(function (Request $request) use ($tokenUrl): bool {
        return $request->url() === $tokenUrl
            && $request['client_id'] === 'test-client-id'
            && $request['client_secret'] === 'test-client-secret'
            && $request['grant_type'] === 'client_credentials'
            && $request['scope'] === 'https://graph.microsoft.com/.default';
    });

    Http::assertSentCount(1);
});

it('resolves a device-code token through the delegated token store', function () {
    $store = new InMemoryDelegatedTokenStore;
    $store->put('personal', [
        'access_token' => 'delegated-access-token',
        'refresh_token' => 'delegated-refresh-token',
        'expires_at' => time() + 3600,
    ]);
    app()->instance(DelegatedTokenStore::class, $store);

    Http::fake([
        '*' => Http::response('unexpected request', 500),
    ]);

    $provider = app(GraphTokenProvider::class);
    $config = [
        'auth_mode' => 'device_code',
        'client_id' => 'public-client-id',
        'tenant_id' => 'consumers',
        'token_key' => 'personal',
    ];

    expect($provider->tokenFor($config))->toBe('delegated-access-token');

    Http::assertNothingSent();
});

it('reads disk configuration from filesystems.disks for tokenForDisk', function () {
    $tokenUrl = 'https://login.microsoftonline.com/disk-tenant/oauth2/v2.0/token';

    config()->set('filesystems.disks.graph_token_test', [
        'driver' => 'sharepoint',
        'auth_mode' => 'client_credentials',
        'client_id' => 'disk-client-id',
        'client_secret' => 'disk-client-secret',
        'tenant_id' => 'disk-tenant',
        'drive_id' => 'disk-drive-id',
    ]);

    Http::fake([
        $tokenUrl => Http::response([
            'access_token' => 'disk-access-token',
            'expires_in' => 3600,
        ], 200),
        '*' => Http::response('unexpected request', 500),
    ]);

    $provider = app(GraphTokenProvider::class);

    expect($provider->tokenForDisk('graph_token_test'))->toBe('disk-access-token');

    Http::assertSent(fn (Request $request): bool => $request->url() === $tokenUrl);
    Http::assertSentCount(1);
});

it('throws a runtime exception for an unsupported auth mode', function () {
    $provider = app(GraphTokenProvider::class);

    $provider->tokenFor([
        'auth_mode' => 'unsupported_mode',
    ]);
})->throws(RuntimeException::class, 'Unsupported SharePoint/OneDrive auth_mode [unsupported_mode].');

it('does not include access tokens in exception messages', function () {
    $store = new InMemoryDelegatedTokenStore;
    $store->put('personal', [
        'access_token' => 'stored-delegated-access-token',
        'refresh_token' => 'stored-refresh-token',
        'expires_at' => time() - 60,
    ]);
    app()->instance(DelegatedTokenStore::class, $store);

    Http::fake([
        'login.microsoftonline.com/*' => Http::response([
            'error' => 'invalid_grant',
            'error_description' => 'The refresh token has expired.',
        ], 400),
        '*' => Http::response('unexpected request', 500),
    ]);

    $provider = app(GraphTokenProvider::class);

    try {
        $provider->tokenFor([
            'auth_mode' => 'device_code',
            'client_id' => 'public-client-id',
            'tenant_id' => 'common',
            'token_key' => 'personal',
        ]);

        expect(false)->toBeTrue('Expected a RuntimeException to be thrown.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())
            ->not->toContain('stored-delegated-access-token')
            ->not->toContain('stored-refresh-token');
    }
});

<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use League\Flysystem\Config;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToMoveFile;
use SahabLibya\SharePointFilesystem\SharePointAdapter;

it('fails safely on a throttled copy monitor without retrying', function (string $operation, string $exceptionClass): void {
    // Characterize the current retry limitation separately from the unknown
    // integration incident. Update this expectation with a future retry fix.
    $parentUrl = 'https://graph.microsoft.com/v1.0/me/drive/items/root-item-id:/backups/Target%20Folder';
    $copyUrl = 'https://graph.microsoft.com/v1.0/me/drive/items/root-item-id:/backups/source.txt:/copy';
    $monitorUrl = 'https://api.onedrive.com/monitor/copy-throttled';

    Http::preventStrayRequests();
    Http::fake([
        $parentUrl => Http::response([
            'id' => 'target-folder-id',
            'parentReference' => ['driveId' => 'personal-drive-id'],
        ], 200),
        $copyUrl => Http::response('', 202, ['Location' => $monitorUrl]),
        $monitorUrl => Http::sequence()
            ->push([
                'error' => [
                    'code' => 'TooManyRequests',
                    'message' => 'Please retry later.',
                ],
            ], 429, ['Retry-After' => '1'])
            ->push(['status' => 'completed'], 200),
    ]);

    $adapter = new SharePointAdapter('test-access-token', null, 'backups', [
        'root_item_id' => 'root-item-id',
        'copy_monitor_timeout' => 5,
        'copy_monitor_interval_ms' => 0,
    ]);

    $caught = null;
    try {
        $adapter->{$operation}('source.txt', 'Target Folder/copied.txt', new Config);
    } catch (Throwable $exception) {
        $caught = $exception;
    }

    expect($caught)->toBeInstanceOf($exceptionClass);
    $cause = $caught;
    while ($cause->getPrevious() !== null) {
        $cause = $cause->getPrevious();
    }
    expect($cause->getMessage())
        ->toContain('Failed to monitor copy operation:', 'TooManyRequests', 'Please retry later.')
        ->not->toContain('test-access-token');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === $parentUrl
    );
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === $copyUrl
        && $request->data() === [
            'parentReference' => ['driveId' => 'personal-drive-id', 'id' => 'target-folder-id'],
            'name' => 'copied.txt',
        ]
    );
    expect(Http::recorded(fn (Request $request): bool => $request->url() === $monitorUrl))->toHaveCount(1);
    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'DELETE');
    Http::assertSentCount(3);
})->with([
    'copy' => ['copy', UnableToCopyFile::class],
    'move preserves the source' => ['move', UnableToMoveFile::class],
]);

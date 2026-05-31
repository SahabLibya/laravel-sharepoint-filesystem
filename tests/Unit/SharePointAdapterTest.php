<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use League\Flysystem\Config;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToMoveFile;
use SahabLibya\SharePointFilesystem\SharePointAdapter;

function sharepointAdapter(string $prefix = '', ?string $driveId = 'drive-id'): SharePointAdapter
{
    return new SharePointAdapter('access-token', $driveId, $prefix, [
        'copy_monitor_timeout' => 1,
        'copy_monitor_interval_ms' => 0,
    ]);
}

function flysystemConfig(): Config
{
    return new Config;
}

it('encodes path segments when writing files', function (): void {
    $url = 'https://graph.microsoft.com/v1.0/drives/drive-id/root:/Folder%20A/%23hash%25/%D9%85%D9%84%D9%81.txt:/content';

    Http::fake([
        $url => Http::response('', 200),
        '*' => Http::response('unexpected request', 500),
    ]);

    sharepointAdapter()->write('Folder A/#hash%/ملف.txt', 'contents', flysystemConfig());

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
        && $request->url() === $url
    );
    Http::assertSentCount(1);
});

it('does not double apply prefixes when copying files', function (): void {
    $parentUrl = 'https://graph.microsoft.com/v1.0/drives/drive-id/root:/base/Target';
    $copyUrl = 'https://graph.microsoft.com/v1.0/drives/drive-id/root:/base/Source%20Folder/file.txt:/copy';
    $monitorUrl = 'https://contoso.sharepoint.com/_api/v2.0/monitor/copy-prefix';

    Http::fake([
        $parentUrl => Http::response(['id' => 'target-id'], 200),
        $copyUrl => Http::response('', 202, ['Location' => $monitorUrl]),
        $monitorUrl => Http::response(['status' => 'completed'], 200),
        '*' => Http::response('unexpected request', 500),
    ]);

    sharepointAdapter('base')->copy('Source Folder/file.txt', 'Target/copied.txt', flysystemConfig());

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === $copyUrl
        && $request['parentReference'] === ['driveId' => 'drive-id', 'id' => 'target-id']
        && $request['name'] === 'copied.txt'
    );
});

it('copies to the root folder using a resolved drive item parent reference', function (): void {
    $rootUrl = 'https://graph.microsoft.com/v1.0/drives/drive-id/root';
    $copyUrl = 'https://graph.microsoft.com/v1.0/drives/drive-id/root:/old.txt:/copy';
    $monitorUrl = 'https://contoso.sharepoint.com/_api/v2.0/monitor/copy-root';

    Http::fake([
        $rootUrl => Http::response(['id' => 'root-id'], 200),
        $copyUrl => Http::response('', 202, ['Location' => $monitorUrl]),
        $monitorUrl => Http::sequence()
            ->push(['status' => 'running'], 200)
            ->push(['status' => 'completed'], 200),
        '*' => Http::response('unexpected request', 500),
    ]);

    sharepointAdapter()->copy('old.txt', 'new.txt', flysystemConfig());

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === $copyUrl
        && $request['parentReference'] === ['driveId' => 'drive-id', 'id' => 'root-id']
        && $request['name'] === 'new.txt'
    );
    Http::assertSentCount(4);
});

it('copies to a subfolder using a resolved drive item parent reference', function (): void {
    $parentUrl = 'https://graph.microsoft.com/v1.0/drives/drive-id/root:/Target%20Folder';
    $copyUrl = 'https://graph.microsoft.com/v1.0/drives/drive-id/root:/old.txt:/copy';
    $monitorUrl = 'https://contoso.sharepoint.com/_api/v2.0/monitor/copy-folder';

    Http::fake([
        $parentUrl => Http::response(['id' => 'folder-id'], 200),
        $copyUrl => Http::response('', 202, ['Location' => $monitorUrl]),
        $monitorUrl => Http::response(['status' => 'completed'], 200),
        '*' => Http::response('unexpected request', 500),
    ]);

    sharepointAdapter()->copy('old.txt', 'Target Folder/new.txt', flysystemConfig());

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === $copyUrl
        && $request['parentReference'] === ['driveId' => 'drive-id', 'id' => 'folder-id']
        && $request['name'] === 'new.txt'
    );
});

it('throws when the copy monitor reports failure', function (): void {
    $rootUrl = 'https://graph.microsoft.com/v1.0/drives/drive-id/root';
    $copyUrl = 'https://graph.microsoft.com/v1.0/drives/drive-id/root:/old.txt:/copy';
    $monitorUrl = 'https://contoso.sharepoint.com/_api/v2.0/monitor/copy-failed';

    Http::fake([
        $rootUrl => Http::response(['id' => 'root-id'], 200),
        $copyUrl => Http::response('', 202, ['Location' => $monitorUrl]),
        $monitorUrl => Http::response([
            'status' => 'failed',
            'error' => ['message' => 'Name already exists'],
        ], 200),
        '*' => Http::response('unexpected request', 500),
    ]);

    expect(fn () => sharepointAdapter()->copy('old.txt', 'new.txt', flysystemConfig()))
        ->toThrow(UnableToCopyFile::class);
});

it('does not delete the source when move copy monitoring fails', function (): void {
    $rootUrl = 'https://graph.microsoft.com/v1.0/drives/drive-id/root';
    $copyUrl = 'https://graph.microsoft.com/v1.0/drives/drive-id/root:/old.txt:/copy';
    $monitorUrl = 'https://contoso.sharepoint.com/_api/v2.0/monitor/move-failed';

    Http::fake([
        $rootUrl => Http::response(['id' => 'root-id'], 200),
        $copyUrl => Http::response('', 202, ['Location' => $monitorUrl]),
        $monitorUrl => Http::response([
            'status' => 'failed',
            'error' => ['message' => 'Name already exists'],
        ], 200),
        '*' => Http::response('unexpected request', 500),
    ]);

    expect(fn () => sharepointAdapter()->move('old.txt', 'new.txt', flysystemConfig()))
        ->toThrow(UnableToMoveFile::class);

    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'DELETE');
});

it('reads all list contents pages from graph pagination', function (): void {
    $firstPageUrl = 'https://graph.microsoft.com/v1.0/drives/drive-id/root/children';
    $secondPageUrl = 'https://graph.microsoft.com/v1.0/drives/drive-id/root/children?page=2';

    Http::fake([
        $firstPageUrl => Http::response([
            'value' => [
                [
                    'name' => 'first.txt',
                    'size' => 10,
                    'file' => ['mimeType' => 'text/plain'],
                    'parentReference' => ['path' => '/drives/drive-id/root:'],
                    'lastModifiedDateTime' => '2025-01-01T00:00:00Z',
                ],
            ],
            '@odata.nextLink' => $secondPageUrl,
        ], 200),
        $secondPageUrl => Http::response([
            'value' => [
                [
                    'name' => 'second.txt',
                    'size' => 20,
                    'file' => ['mimeType' => 'text/plain'],
                    'parentReference' => ['path' => '/drives/drive-id/root:/Folder%20A'],
                    'lastModifiedDateTime' => '2025-01-02T00:00:00Z',
                ],
            ],
        ], 200),
        '*' => Http::response('unexpected request', 500),
    ]);

    $items = iterator_to_array(sharepointAdapter()->listContents('', false));

    expect($items)->toHaveCount(2)
        ->and($items[0]->path())->toBe('first.txt')
        ->and($items[1]->path())->toBe('Folder A/second.txt');
});

it('returns unix timestamps for mime type metadata', function (): void {
    $url = 'https://graph.microsoft.com/v1.0/drives/drive-id/root:/report.pdf';
    $lastModified = '2025-01-01T12:34:56Z';

    Http::fake([
        $url => Http::response([
            'size' => 123,
            'lastModifiedDateTime' => $lastModified,
            'file' => ['mimeType' => 'application/pdf'],
        ], 200),
        '*' => Http::response('unexpected request', 500),
    ]);

    $attributes = sharepointAdapter()->mimeType('report.pdf');

    expect($attributes->mimeType())->toBe('application/pdf')
        ->and($attributes->lastModified())->toBe(strtotime($lastModified));
});

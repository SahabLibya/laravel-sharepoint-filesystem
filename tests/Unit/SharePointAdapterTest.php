<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use League\Flysystem\Config;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use SahabLibya\SharePointFilesystem\SharePointAdapter;

function sharepointAdapter(
    string $prefix = '',
    ?string $driveId = 'drive-id',
    ?string $rootItemId = null
): SharePointAdapter {
    return new SharePointAdapter('access-token', $driveId, $prefix, [
        'copy_monitor_timeout' => 1,
        'copy_monitor_interval_ms' => 0,
        'root_item_id' => $rootItemId,
    ]);
}

function rootItemAdapter(
    string $prefix = '',
    ?string $driveId = 'drive-id',
    string $rootItemId = 'root-item-id'
): SharePointAdapter {
    return sharepointAdapter($prefix, $driveId, $rootItemId);
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

it('uses a configured drive item as the root for path-based requests', function (): void {
    $url = 'https://graph.microsoft.com/v1.0/drives/drive-id/items/root-item-id:/fssi-backup/example.zip:/content';

    Http::fake([
        $url => Http::response(['id' => 'uploaded-item'], 201),
        '*' => Http::response('unexpected request', 500),
    ]);

    rootItemAdapter()->write('fssi-backup/example.zip', 'backup contents', flysystemConfig());

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
        && $request->url() === $url
    );
    Http::assertSentCount(1);
});

it('addresses an empty logical path as the configured root item', function (): void {
    $url = 'https://graph.microsoft.com/v1.0/drives/drive-id/items/root-item-id';

    Http::fake([
        $url => Http::response(['id' => 'root-item-id', 'folder' => new stdClass], 200),
        '*' => Http::response('unexpected request', 500),
    ]);

    expect(rootItemAdapter()->directoryExists(''))->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === $url
    );
    Http::assertSentCount(1);
});

it('lists the configured root item children', function (): void {
    $url = 'https://graph.microsoft.com/v1.0/drives/drive-id/items/root-item-id/children';

    Http::fake([
        $url => Http::response([
            'value' => [[
                'name' => 'report.txt',
                'size' => 12,
                'file' => ['mimeType' => 'text/plain'],
                'parentReference' => [
                    'path' => '/drives/drive-id/root:/Physical%20Parent',
                ],
            ]],
        ], 200),
        '*' => Http::response('unexpected request', 500),
    ]);

    $items = iterator_to_array(rootItemAdapter()->listContents('', false));

    expect($items)->toHaveCount(1)
        ->and($items[0]->path())->toBe('report.txt');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === $url
    );
});

it('lists nested directories relative to the configured root item', function (): void {
    $url = 'https://graph.microsoft.com/v1.0/drives/drive-id/items/root-item-id:/Folder%20A:/children';

    Http::fake([
        $url => Http::response([
            'value' => [[
                'name' => 'report.txt',
                'file' => ['mimeType' => 'text/plain'],
                'parentReference' => [
                    'path' => '/drives/drive-id/root:/Physical%20Parent/Folder%20A',
                ],
            ]],
        ], 200),
        '*' => Http::response('unexpected request', 500),
    ]);

    $items = iterator_to_array(rootItemAdapter()->listContents('Folder A', false));

    expect($items)->toHaveCount(1)
        ->and($items[0]->path())->toBe('Folder A/report.txt');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === $url
    );
});

it('composes a path prefix inside the configured root item', function (): void {
    $url = 'https://graph.microsoft.com/v1.0/drives/drive-id/items/root-item-id:/backups/daily.zip:/content';

    Http::fake([
        $url => Http::response(['id' => 'uploaded-item'], 201),
        '*' => Http::response('unexpected request', 500),
    ]);

    rootItemAdapter('backups')->write('daily.zip', 'contents', flysystemConfig());

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
        && $request->url() === $url
    );
});

it('encodes the root item id as one segment and encodes each logical path segment', function (): void {
    $url = 'https://graph.microsoft.com/v1.0/drives/drive-id/items/root%2Fitem%20%23%25:/Folder%20A/%D9%85%D9%84%D9%81%23%25.txt:/content';

    Http::fake([
        $url => Http::response(['id' => 'uploaded-item'], 201),
        '*' => Http::response('unexpected request', 500),
    ]);

    rootItemAdapter('', 'drive-id', '  root/item #%  ')
        ->write('Folder A/ملف#%.txt', 'contents', flysystemConfig());

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
        && $request->url() === $url
    );
});

it('uses item-relative endpoints for content and directory operations', function (): void {
    $writeUrl = 'https://graph.microsoft.com/v1.0/drives/drive-id/items/root-item-id:/write.txt:/content';
    $streamWriteUrl = 'https://graph.microsoft.com/v1.0/drives/drive-id/items/root-item-id:/stream.txt:/content';
    $readUrl = 'https://graph.microsoft.com/v1.0/drives/drive-id/items/root-item-id:/read.txt:/content';
    $streamReadUrl = 'https://graph.microsoft.com/v1.0/drives/drive-id/items/root-item-id:/stream-read.txt:/content';
    $deleteUrl = 'https://graph.microsoft.com/v1.0/drives/drive-id/items/root-item-id:/delete.txt';
    $deleteDirectoryUrl = 'https://graph.microsoft.com/v1.0/drives/drive-id/items/root-item-id:/old-folder';
    $createDirectoryUrl = 'https://graph.microsoft.com/v1.0/drives/drive-id/items/root-item-id:/Parent:/children';

    Http::fake([
        $writeUrl => Http::response(['id' => 'write-item'], 201),
        $streamWriteUrl => Http::response(['id' => 'stream-item'], 201),
        $readUrl => Http::response('read contents', 200),
        $streamReadUrl => Http::response('stream contents', 200),
        $deleteUrl => Http::response('', 204),
        $deleteDirectoryUrl => Http::response('', 204),
        $createDirectoryUrl => Http::response(['id' => 'folder-item'], 201),
        '*' => Http::response('unexpected request', 500),
    ]);

    $adapter = rootItemAdapter();
    $adapter->write('write.txt', 'write contents', flysystemConfig());

    $uploadStream = fopen('php://temp', 'r+');
    fwrite($uploadStream, 'stream contents');
    rewind($uploadStream);
    $adapter->writeStream('stream.txt', $uploadStream, flysystemConfig());
    fclose($uploadStream);

    expect($adapter->read('read.txt'))->toBe('read contents');

    $downloadStream = $adapter->readStream('stream-read.txt');
    expect(stream_get_contents($downloadStream))->toBe('stream contents');
    fclose($downloadStream);

    $adapter->delete('delete.txt');
    $adapter->deleteDirectory('old-folder');
    $adapter->createDirectory('Parent/New Folder', flysystemConfig());

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === $createDirectoryUrl
        && $request['name'] === 'New Folder'
        && $request['@microsoft.graph.conflictBehavior'] === 'rename'
    );
    Http::assertSentCount(7);
});

it('uses the configured root item for existence and file metadata requests', function (): void {
    $fileUrl = 'https://graph.microsoft.com/v1.0/drives/drive-id/items/root-item-id:/report.pdf';
    $folderUrl = 'https://graph.microsoft.com/v1.0/drives/drive-id/items/root-item-id:/Archive';
    $lastModified = '2026-07-23T10:20:30Z';

    Http::fake([
        $fileUrl => Http::response([
            'size' => 321,
            'lastModifiedDateTime' => $lastModified,
            'file' => ['mimeType' => 'application/pdf'],
        ], 200),
        $folderUrl => Http::response(['folder' => new stdClass], 200),
        '*' => Http::response('unexpected request', 500),
    ]);

    $adapter = rootItemAdapter();

    expect($adapter->fileExists('report.pdf'))->toBeTrue()
        ->and($adapter->directoryExists('Archive'))->toBeTrue()
        ->and($adapter->fileSize('report.pdf')->fileSize())->toBe(321)
        ->and($adapter->mimeType('report.pdf')->mimeType())->toBe('application/pdf')
        ->and($adapter->lastModified('report.pdf')->lastModified())->toBe(strtotime($lastModified));
});

it('does not delete or move the configured root item through an empty logical path', function (): void {
    Http::fake();

    $adapter = rootItemAdapter();

    expect(fn () => $adapter->delete(''))->toThrow(UnableToDeleteFile::class)
        ->and(fn () => $adapter->deleteDirectory('/'))->toThrow(UnableToDeleteDirectory::class)
        ->and(fn () => $adapter->move('', 'moved-root', flysystemConfig()))
        ->toThrow(UnableToMoveFile::class);

    Http::assertNothingSent();
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

it('keeps copy and move destinations inside the configured root item', function (): void {
    $rootParentUrl = 'https://graph.microsoft.com/v1.0/drives/drive-id/items/root-item-id';
    $archiveParentUrl = 'https://graph.microsoft.com/v1.0/drives/drive-id/items/root-item-id:/Archive';
    $copyUrl = 'https://graph.microsoft.com/v1.0/drives/drive-id/items/root-item-id:/source.txt:/copy';
    $moveCopyUrl = 'https://graph.microsoft.com/v1.0/drives/drive-id/items/root-item-id:/move-source.txt:/copy';
    $moveDeleteUrl = 'https://graph.microsoft.com/v1.0/drives/drive-id/items/root-item-id:/move-source.txt';
    $copyMonitorUrl = 'https://contoso.sharepoint.com/_api/v2.0/monitor/item-root-copy';
    $moveMonitorUrl = 'https://contoso.sharepoint.com/_api/v2.0/monitor/item-root-move';

    Http::fake([
        $rootParentUrl => Http::response(['id' => 'root-item-id'], 200),
        $archiveParentUrl => Http::response(['id' => 'archive-id'], 200),
        $copyUrl => Http::response('', 202, ['Location' => $copyMonitorUrl]),
        $moveCopyUrl => Http::response('', 202, ['Location' => $moveMonitorUrl]),
        $copyMonitorUrl => Http::response(['status' => 'completed'], 200),
        $moveMonitorUrl => Http::response(['status' => 'completed'], 200),
        $moveDeleteUrl => Http::response('', 204),
        '*' => Http::response('unexpected request', 500),
    ]);

    $adapter = rootItemAdapter();
    $adapter->copy('source.txt', 'copied.txt', flysystemConfig());
    $adapter->move('move-source.txt', 'Archive/moved.txt', flysystemConfig());

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === $copyUrl
        && $request['parentReference'] === ['driveId' => 'drive-id', 'id' => 'root-item-id']
        && $request['name'] === 'copied.txt'
    );
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === $moveCopyUrl
        && $request['parentReference'] === ['driveId' => 'drive-id', 'id' => 'archive-id']
        && $request['name'] === 'moved.txt'
    );
    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
        && $request->url() === $moveDeleteUrl
    );
    Http::assertSentCount(7);
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

it('returns clean logical paths for recursive item-root listings with a prefix and pagination', function (): void {
    $firstPageUrl = 'https://graph.microsoft.com/v1.0/drives/drive-id/items/root-item-id:/base:/children';
    $secondPageUrl = 'https://graph.microsoft.com/v1.0/drives/drive-id/items/root-item-id:/base:/children?page=2';
    $nestedUrl = 'https://graph.microsoft.com/v1.0/drives/drive-id/items/root-item-id:/base/Folder%20A:/children';

    Http::fake([
        $firstPageUrl => Http::response([
            'value' => [[
                'name' => 'Folder A',
                'folder' => ['childCount' => 1],
                'parentReference' => [
                    'path' => '/drives/drive-id/root:/Physical%20Parent/base',
                ],
            ]],
            '@odata.nextLink' => $secondPageUrl,
        ], 200),
        $nestedUrl => Http::response([
            'value' => [[
                'name' => 'inside.txt',
                'file' => ['mimeType' => 'text/plain'],
                'parentReference' => [
                    'path' => '/drives/drive-id/root:/Physical%20Parent/base/Folder%20A',
                ],
            ]],
        ], 200),
        $secondPageUrl => Http::response([
            'value' => [[
                'name' => 'page-two.txt',
                'file' => ['mimeType' => 'text/plain'],
                'parentReference' => [
                    'path' => '/drives/drive-id/root:/Physical%20Parent/base',
                ],
            ]],
        ], 200),
        '*' => Http::response('unexpected request', 500),
    ]);

    $items = iterator_to_array(rootItemAdapter('base')->listContents('', true));
    $paths = array_map(
        static fn ($attributes): string => $attributes->path(),
        $items
    );

    expect($paths)->toBe([
        'Folder A',
        'Folder A/inside.txt',
        'page-two.txt',
    ]);
    Http::assertSentCount(3);
});

it('uses the signed-in user drive when an item root has no configured drive id', function (): void {
    $url = 'https://graph.microsoft.com/v1.0/me/drive/items/root-item-id:/personal.txt:/content';

    Http::fake([
        $url => Http::response(['id' => 'uploaded-item'], 201),
        '*' => Http::response('unexpected request', 500),
    ]);

    rootItemAdapter('', null)->write('personal.txt', 'contents', flysystemConfig());

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
        && $request->url() === $url
    );
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

<?php

declare(strict_types=1);

namespace SahabLibya\SharePointFilesystem;

use GuzzleHttp\Psr7\Stream;
use Illuminate\Support\Facades\Http;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathPrefixer;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use RuntimeException;
use Throwable;

class SharePointAdapter implements FilesystemAdapter
{
    private PathPrefixer $prefixer;

    private string $baseUrl = 'https://graph.microsoft.com/v1.0';

    private int $copyMonitorTimeout;

    private int $copyMonitorIntervalMs;

    public function __construct(
        private string $accessToken,
        private ?string $driveId = null,
        string $prefix = '',
        array $options = [],
    ) {
        $this->prefixer = new PathPrefixer($prefix);
        $this->copyMonitorTimeout = max(1, (int) ($options['copy_monitor_timeout'] ?? 300));
        $this->copyMonitorIntervalMs = max(0, (int) ($options['copy_monitor_interval_ms'] ?? 1000));
    }

    public function fileExists(string $path): bool
    {
        try {
            $this->getMetadata($path);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function directoryExists(string $path): bool
    {
        try {
            $metadata = $this->getMetadata($path);

            return isset($metadata['folder']);
        } catch (Throwable) {
            return false;
        }
    }

    public function write(string $path, string $contents, Config $config): void
    {
        try {
            $endpoint = $this->baseUrl.$this->driveItemPath($path).':/content';

            $response = Http::withToken($this->accessToken)
                ->timeout(300)
                ->withBody($contents, 'application/octet-stream')
                ->put($endpoint);

            if ($response->failed()) {
                throw new RuntimeException('Failed to write file: '.$response->body());
            }
        } catch (Throwable $exception) {
            throw UnableToWriteFile::atLocation($path, $exception->getMessage(), $exception);
        }
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        try {
            if (is_resource($contents)) {
                $endpoint = $this->baseUrl.$this->driveItemPath($path).':/content';

                $response = Http::withToken($this->accessToken)
                    ->timeout(300)
                    ->withBody($contents, 'application/octet-stream')
                    ->put($endpoint);

                if ($response->failed()) {
                    throw new RuntimeException('Failed to write file: '.$response->body());
                }

                return;
            }

            $stream = $contents instanceof Stream ? $contents : new Stream($contents);
            $this->write($path, $stream->getContents(), $config);
        } catch (Throwable $exception) {
            throw UnableToWriteFile::atLocation($path, $exception->getMessage(), $exception);
        }
    }

    public function read(string $path): string
    {
        try {
            $endpoint = $this->baseUrl.$this->driveItemPath($path).':/content';
            $response = Http::withToken($this->accessToken)
                ->get($endpoint);

            if ($response->failed()) {
                throw new RuntimeException('Failed to read file: '.$response->body());
            }

            return $response->body();
        } catch (Throwable $exception) {
            throw UnableToReadFile::fromLocation($path, $exception->getMessage(), $exception);
        }
    }

    public function readStream(string $path)
    {
        try {
            $contents = $this->read($path);
            $stream = fopen('php://temp', 'r+');
            fwrite($stream, $contents);
            rewind($stream);

            return $stream;
        } catch (Throwable $exception) {
            throw UnableToReadFile::fromLocation($path, $exception->getMessage(), $exception);
        }
    }

    public function delete(string $path): void
    {
        try {
            $endpoint = $this->baseUrl.$this->driveItemPath($path);
            $response = Http::withToken($this->accessToken)
                ->delete($endpoint);

            if ($response->failed()) {
                throw new RuntimeException('Failed to delete file: '.$response->body());
            }
        } catch (Throwable $exception) {
            throw UnableToDeleteFile::atLocation($path, $exception->getMessage(), $exception);
        }
    }

    public function deleteDirectory(string $path): void
    {
        try {
            $endpoint = $this->baseUrl.$this->driveItemPath($path);
            $response = Http::withToken($this->accessToken)
                ->delete($endpoint);

            if ($response->failed()) {
                throw new RuntimeException('Failed to delete directory: '.$response->body());
            }
        } catch (Throwable $exception) {
            throw UnableToDeleteDirectory::atLocation($path, $exception->getMessage(), $exception);
        }
    }

    public function createDirectory(string $path, Config $config): void
    {
        try {
            $prefixedPath = $this->prefixedPath($path);
            $parts = explode('/', trim($prefixedPath, '/'));
            $folderName = array_pop($parts);
            $parentPath = implode('/', $parts);

            $endpoint = $this->baseUrl.$this->childrenPath($parentPath, true);

            $response = Http::withToken($this->accessToken)
                ->post($endpoint, [
                    'name' => $folderName,
                    'folder' => new \stdClass,
                    '@microsoft.graph.conflictBehavior' => 'rename',
                ]);

            if ($response->failed()) {
                throw new RuntimeException('Failed to create directory: '.$response->body());
            }
        } catch (Throwable $exception) {
            throw UnableToCreateDirectory::dueToFailure($path, $exception);
        }
    }

    public function setVisibility(string $path, string $visibility): void
    {
        throw UnableToSetVisibility::atLocation($path, 'SharePoint/OneDrive does not support visibility settings.');
    }

    public function visibility(string $path): FileAttributes
    {
        throw UnableToRetrieveMetadata::visibility($path, 'SharePoint/OneDrive does not support visibility settings.');
    }

    public function mimeType(string $path): FileAttributes
    {
        $metadata = $this->getMetadata($path);

        return new FileAttributes(
            $path,
            $metadata['size'] ?? null,
            null,
            $this->lastModifiedTimestamp($metadata),
            $metadata['file']['mimeType'] ?? null
        );
    }

    public function lastModified(string $path): FileAttributes
    {
        $metadata = $this->getMetadata($path);

        return new FileAttributes($path, null, null, $this->lastModifiedTimestamp($metadata));
    }

    public function fileSize(string $path): FileAttributes
    {
        $metadata = $this->getMetadata($path);

        return new FileAttributes($path, $metadata['size'] ?? null);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        try {
            $endpoint = $this->baseUrl.$this->childrenPath($path);

            while ($endpoint) {
                $response = Http::withToken($this->accessToken)
                    ->get($endpoint);

                if ($response->failed()) {
                    return;
                }

                $payload = $response->json();
                $items = $payload['value'] ?? [];

                foreach ($items as $item) {
                    $itemPath = $this->pathFromListItem($item);

                    if (isset($item['folder'])) {
                        yield new DirectoryAttributes(
                            $itemPath,
                            null,
                            $this->lastModifiedTimestamp($item)
                        );

                        if ($deep) {
                            yield from $this->listContents($itemPath, true);
                        }

                        continue;
                    }

                    yield new FileAttributes(
                        $itemPath,
                        $item['size'] ?? null,
                        null,
                        $this->lastModifiedTimestamp($item),
                        $item['file']['mimeType'] ?? null
                    );
                }

                $endpoint = $payload['@odata.nextLink'] ?? null;
            }
        } catch (Throwable) {
            return;
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $this->copy($source, $destination, $config);
            $this->delete($source);
        } catch (Throwable $exception) {
            throw UnableToMoveFile::fromLocationTo($source, $destination, $exception);
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $prefixedDestination = $this->prefixedPath($destination);
            $parts = explode('/', trim($prefixedDestination, '/'));
            $newName = array_pop($parts);
            $parentPath = implode('/', $parts);

            if ($newName === null || $newName === '') {
                throw new RuntimeException('The copy destination must include a file or folder name.');
            }

            $parentReference = $this->parentReferenceForCopy($parentPath);
            $endpoint = $this->baseUrl.$this->driveItemPath($source).':/copy';

            $response = Http::withToken($this->accessToken)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($endpoint, [
                    'parentReference' => $parentReference,
                    'name' => $newName,
                ]);

            if ($response->failed()) {
                throw new RuntimeException('Failed to copy file: '.$response->body());
            }

            if ($response->status() !== 202) {
                throw new RuntimeException('Failed to copy file: expected a 202 Accepted response.');
            }

            $monitorUrl = $response->header('Location');

            if (! is_string($monitorUrl) || $monitorUrl === '') {
                throw new RuntimeException('Failed to copy file: missing copy monitor URL.');
            }

            $this->monitorCopyOperation($monitorUrl);
        } catch (Throwable $exception) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $exception);
        }
    }

    private function getMetadata(string $path, bool $pathIsPrefixed = false): array
    {
        try {
            $endpoint = $this->baseUrl.$this->driveItemPath($path, $pathIsPrefixed);
            $response = Http::withToken($this->accessToken)
                ->get($endpoint);

            if ($response->failed()) {
                throw new RuntimeException('Failed to get metadata: '.$response->body());
            }

            return $response->json();
        } catch (Throwable $exception) {
            throw UnableToRetrieveMetadata::create($path, 'metadata', $exception->getMessage(), $exception);
        }
    }

    private function driveRootPath(): string
    {
        if ($this->driveId) {
            return "/drives/{$this->driveId}/root";
        }

        return '/me/drive/root';
    }

    private function driveItemPath(string $path, bool $pathIsPrefixed = false): string
    {
        $encodedPath = $this->encodedPath($path, $pathIsPrefixed);

        if ($encodedPath === '') {
            return $this->driveRootPath();
        }

        return $this->driveRootPath().':/'.$encodedPath;
    }

    private function childrenPath(string $path, bool $pathIsPrefixed = false): string
    {
        $encodedPath = $this->encodedPath($path, $pathIsPrefixed);

        if ($encodedPath === '') {
            return $this->driveRootPath().'/children';
        }

        return $this->driveRootPath().':/'.$encodedPath.':/children';
    }

    private function prefixedPath(string $path): string
    {
        return $this->normalizePath($this->prefixer->prefixPath($path));
    }

    private function encodedPath(string $path, bool $pathIsPrefixed = false): string
    {
        $normalizedPath = $pathIsPrefixed
            ? $this->normalizePath($path)
            : $this->prefixedPath($path);

        if ($normalizedPath === '') {
            return '';
        }

        $segments = array_map(
            static fn (string $segment): string => rawurlencode($segment),
            explode('/', $normalizedPath)
        );

        return implode('/', $segments);
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $segments = array_values(array_filter(
            explode('/', $path),
            static fn (string $segment): bool => $segment !== ''
        ));

        return implode('/', $segments);
    }

    private function parentReferenceForCopy(string $prefixedParentPath): array
    {
        $metadata = $this->getMetadata($prefixedParentPath, true);
        $parentId = $metadata['id'] ?? null;
        $driveId = $this->driveId
            ?? ($metadata['parentReference']['driveId'] ?? null)
            ?? ($metadata['remoteItem']['parentReference']['driveId'] ?? null);

        if (! is_string($parentId) || $parentId === '') {
            throw new RuntimeException('Failed to copy file: destination parent folder is missing an id.');
        }

        if (! is_string($driveId) || $driveId === '') {
            throw new RuntimeException('Failed to copy file: destination parent folder is missing a driveId.');
        }

        return [
            'driveId' => $driveId,
            'id' => $parentId,
        ];
    }

    private function monitorCopyOperation(string $monitorUrl): void
    {
        $deadline = microtime(true) + $this->copyMonitorTimeout;

        while (true) {
            $response = Http::withToken($this->accessToken)
                ->get($monitorUrl);

            if ($response->failed()) {
                throw new RuntimeException('Failed to monitor copy operation: '.$response->body());
            }

            $payload = $response->json();
            $status = strtolower((string) ($payload['status'] ?? ''));

            if ($status === 'completed') {
                return;
            }

            if ($status === 'failed') {
                throw new RuntimeException('Failed to copy file: '.$this->copyMonitorErrorMessage($payload));
            }

            if ($status === '') {
                throw new RuntimeException('Failed to monitor copy operation: missing status.');
            }

            if (microtime(true) >= $deadline) {
                throw new RuntimeException('Timed out while waiting for copy operation to complete.');
            }

            if ($this->copyMonitorIntervalMs > 0) {
                usleep($this->copyMonitorIntervalMs * 1000);
            }
        }
    }

    private function copyMonitorErrorMessage(array $payload): string
    {
        $message = $payload['error']['message'] ?? null;

        if (is_string($message) && $message !== '') {
            return $message;
        }

        return 'the Microsoft Graph copy monitor reported failure.';
    }

    private function pathFromListItem(array $item): string
    {
        $parentPath = $item['parentReference']['path'] ?? '';
        $itemName = $item['name'] ?? '';

        if (preg_match('/root:(.*)/', (string) $parentPath, $matches)) {
            $relativePath = trim(rawurldecode($matches[1]), '/');
            $itemPath = $relativePath ? $relativePath.'/'.$itemName : $itemName;
        } else {
            $itemPath = (string) $itemName;
        }

        return $this->prefixer->stripPrefix($this->normalizePath($itemPath));
    }

    private function lastModifiedTimestamp(array $metadata): ?int
    {
        if (! isset($metadata['lastModifiedDateTime'])) {
            return null;
        }

        $timestamp = strtotime((string) $metadata['lastModifiedDateTime']);

        return $timestamp === false ? null : $timestamp;
    }
}

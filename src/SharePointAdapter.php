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
use Throwable;

class SharePointAdapter implements FilesystemAdapter
{
    private PathPrefixer $prefixer;

    private string $baseUrl = 'https://graph.microsoft.com/v1.0';

    public function __construct(
        private string $accessToken,
        private ?string $driveId = null,
        string $prefix = ''
    ) {
        $this->prefixer = new PathPrefixer($prefix);
    }

    private function getBasePath(string $path): string
    {
        $path = $this->prefixer->prefixPath($path);

        if ($this->driveId) {
            return "/drives/{$this->driveId}/root:/{$path}";
        }

        return "/me/drive/root:/{$path}";
    }

    private function getChildrenPath(string $path): string
    {
        $path = $this->prefixer->prefixPath($path);

        if ($this->driveId) {
            return empty($path) || $path === '/'
                ? "/drives/{$this->driveId}/root/children"
                : "/drives/{$this->driveId}/root:/{$path}:/children";
        }

        return empty($path) || $path === '/'
            ? '/me/drive/root/children'
            : "/me/drive/root:/{$path}:/children";
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
            $endpoint = $this->baseUrl.$this->getBasePath($path).':/content';
            // Increase timeout for large files (5 minutes)
            $response = Http::withToken($this->accessToken)
                ->timeout(300)
                ->withBody($contents, 'application/octet-stream')
                ->put($endpoint);

            if ($response->failed()) {
                throw new \RuntimeException('Failed to write file: '.$response->body());
            }
        } catch (Throwable $exception) {
            throw UnableToWriteFile::atLocation($path, $exception->getMessage(), $exception);
        }
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        try {
            // For large files, we need to read the stream properly without loading all into memory at once
            if (is_resource($contents)) {
                $endpoint = $this->baseUrl.$this->getBasePath($path).':/content';
                // Increase timeout for large files (5 minutes)
                $response = Http::withToken($this->accessToken)
                    ->timeout(300)
                    ->withBody($contents, 'application/octet-stream')
                    ->put($endpoint);

                if ($response->failed()) {
                    throw new \RuntimeException('Failed to write file: '.$response->body());
                }
            } else {
                $stream = $contents instanceof Stream ? $contents : new Stream($contents);
                $this->write($path, $stream->getContents(), $config);
            }
        } catch (Throwable $exception) {
            throw UnableToWriteFile::atLocation($path, $exception->getMessage(), $exception);
        }
    }

    public function read(string $path): string
    {
        try {
            $endpoint = $this->baseUrl.$this->getBasePath($path).':/content';
            $response = Http::withToken($this->accessToken)
                ->get($endpoint);

            if ($response->failed()) {
                throw new \RuntimeException('Failed to read file: '.$response->body());
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
            $endpoint = $this->baseUrl.$this->getBasePath($path);
            $response = Http::withToken($this->accessToken)
                ->delete($endpoint);

            if ($response->failed()) {
                throw new \RuntimeException('Failed to delete file: '.$response->body());
            }
        } catch (Throwable $exception) {
            throw UnableToDeleteFile::atLocation($path, $exception->getMessage(), $exception);
        }
    }

    public function deleteDirectory(string $path): void
    {
        try {
            $endpoint = $this->baseUrl.$this->getBasePath($path);
            $response = Http::withToken($this->accessToken)
                ->delete($endpoint);

            if ($response->failed()) {
                throw new \RuntimeException('Failed to delete directory: '.$response->body());
            }
        } catch (Throwable $exception) {
            throw UnableToDeleteDirectory::atLocation($path, $exception->getMessage(), $exception);
        }
    }

    public function createDirectory(string $path, Config $config): void
    {
        try {
            $path = $this->prefixer->prefixPath($path);
            $parts = explode('/', trim($path, '/'));
            $folderName = array_pop($parts);
            $parentPath = implode('/', $parts);

            $endpoint = $this->driveId
                ? (empty($parentPath)
                    ? "{$this->baseUrl}/drives/{$this->driveId}/root/children"
                    : "{$this->baseUrl}/drives/{$this->driveId}/root:/{$parentPath}:/children")
                : (empty($parentPath)
                    ? "{$this->baseUrl}/me/drive/root/children"
                    : "{$this->baseUrl}/me/drive/root:/{$parentPath}:/children");

            $response = Http::withToken($this->accessToken)
                ->post($endpoint, [
                    'name' => $folderName,
                    'folder' => new \stdClass,
                    '@microsoft.graph.conflictBehavior' => 'rename',
                ]);

            if ($response->failed()) {
                throw new \RuntimeException('Failed to create directory: '.$response->body());
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
            $metadata['lastModifiedDateTime'] ?? null,
            $metadata['file']['mimeType'] ?? null
        );
    }

    public function lastModified(string $path): FileAttributes
    {
        $metadata = $this->getMetadata($path);
        $timestamp = isset($metadata['lastModifiedDateTime'])
            ? strtotime($metadata['lastModifiedDateTime'])
            : null;

        return new FileAttributes($path, null, null, $timestamp);
    }

    public function fileSize(string $path): FileAttributes
    {
        $metadata = $this->getMetadata($path);

        return new FileAttributes($path, $metadata['size'] ?? null);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        try {
            $endpoint = $this->baseUrl.$this->getChildrenPath($path);
            $response = Http::withToken($this->accessToken)
                ->get($endpoint);

            if ($response->failed()) {
                return;
            }

            $items = $response->json()['value'] ?? [];

            foreach ($items as $item) {
                // Fix path construction to properly handle path prefixes
                $parentPath = $item['parentReference']['path'] ?? '';
                $itemName = $item['name'] ?? '';
                
                // Extract the relative path from the parent reference
                if (preg_match('/root:(.*)/', $parentPath, $matches)) {
                    $relativePath = trim($matches[1], '/');
                    $itemPath = $relativePath ? $relativePath.'/'.$itemName : $itemName;
                } else {
                    $itemPath = $itemName;
                }
                
                $itemPath = $this->prefixer->stripPrefix($itemPath);

                if (isset($item['folder'])) {
                    yield new DirectoryAttributes(
                        $itemPath,
                        null,
                        isset($item['lastModifiedDateTime'])
                            ? strtotime($item['lastModifiedDateTime'])
                            : null
                    );

                    if ($deep) {
                        yield from $this->listContents($itemPath, true);
                    }
                } else {
                    yield new FileAttributes(
                        $itemPath,
                        $item['size'] ?? null,
                        null,
                        isset($item['lastModifiedDateTime'])
                            ? strtotime($item['lastModifiedDateTime'])
                            : null,
                        $item['file']['mimeType'] ?? null
                    );
                }
            }
        } catch (Throwable $exception) {
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
            $source = $this->prefixer->prefixPath($source);
            $destination = $this->prefixer->prefixPath($destination);

            $parts = explode('/', trim($destination, '/'));
            $newName = array_pop($parts);
            $parentPath = implode('/', $parts);

            $drivePrefix = $this->driveId ? "/drives/{$this->driveId}" : '/drive';
            $parentReference = empty($parentPath)
                ? ['path' => "{$drivePrefix}/root"]
                : ['path' => "{$drivePrefix}/root/{$parentPath}"];

            $endpoint = $this->baseUrl.$this->getBasePath($source).':/copy';

            $response = Http::withToken($this->accessToken)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($endpoint, [
                    'parentReference' => $parentReference,
                    'name' => $newName,
                ]);

            if ($response->failed()) {
                throw new \RuntimeException('Failed to copy file: '.$response->body());
            }
        } catch (Throwable $exception) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $exception);
        }
    }

    private function getMetadata(string $path): array
    {
        try {
            $endpoint = $this->baseUrl.$this->getBasePath($path);
            $response = Http::withToken($this->accessToken)
                ->get($endpoint);

            if ($response->failed()) {
                throw new \RuntimeException('Failed to get metadata: '.$response->body());
            }

            return $response->json();
        } catch (Throwable $exception) {
            throw UnableToRetrieveMetadata::create($path, 'metadata', $exception->getMessage(), $exception);
        }
    }
}

<?php

declare(strict_types=1);

namespace SahabLibya\SharePointFilesystem\Authentication;

use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Filesystem\Filesystem;
use JsonException;
use RuntimeException;
use Throwable;

final class EncryptedFileTokenStore implements DelegatedTokenStore
{
    public function __construct(
        private Filesystem $files,
        private Encrypter $encrypter,
        private string $directory,
    ) {}

    public function get(string $key): ?array
    {
        $path = $this->path($key);

        if (! $this->files->exists($path)) {
            return null;
        }

        try {
            $json = $this->encrypter->decrypt($this->files->get($path), false);
            $tokens = json_decode((string) $json, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'Unable to read the stored OneDrive connection. Run php artisan onedrive:connect again.',
                previous: $exception,
            );
        }

        if (! is_array($tokens)) {
            throw new RuntimeException(
                'The stored OneDrive connection is invalid. Run php artisan onedrive:connect again.'
            );
        }

        return $tokens;
    }

    public function put(string $key, array $tokens): void
    {
        try {
            $json = json_encode($tokens, JSON_THROW_ON_ERROR);
            $encrypted = $this->encrypter->encrypt($json, false);
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode the OneDrive connection.', previous: $exception);
        }

        $this->files->ensureDirectoryExists($this->directory, 0700);
        $this->files->replace($this->path($key), $encrypted, 0600);
    }

    public function forget(string $key): void
    {
        $path = $this->path($key);

        if ($this->files->exists($path)) {
            $this->files->delete($path);
        }
    }

    private function path(string $key): string
    {
        return rtrim($this->directory, DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR
            .hash('sha256', $key)
            .'.token';
    }
}

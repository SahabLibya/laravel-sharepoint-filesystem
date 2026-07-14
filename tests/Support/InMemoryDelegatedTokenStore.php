<?php

declare(strict_types=1);

namespace SahabLibya\SharePointFilesystem\Tests\Support;

use SahabLibya\SharePointFilesystem\Authentication\DelegatedTokenStore;

final class InMemoryDelegatedTokenStore implements DelegatedTokenStore
{
    public array $connections = [];

    public function get(string $key): ?array
    {
        return $this->connections[$key] ?? null;
    }

    public function put(string $key, array $tokens): void
    {
        $this->connections[$key] = $tokens;
    }

    public function forget(string $key): void
    {
        unset($this->connections[$key]);
    }
}

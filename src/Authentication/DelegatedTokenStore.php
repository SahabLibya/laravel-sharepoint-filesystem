<?php

declare(strict_types=1);

namespace SahabLibya\SharePointFilesystem\Authentication;

interface DelegatedTokenStore
{
    public function get(string $key): ?array;

    public function put(string $key, array $tokens): void;

    public function forget(string $key): void;
}

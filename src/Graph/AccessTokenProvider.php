<?php

declare(strict_types=1);

namespace SahabLibya\SharePointFilesystem\Graph;

interface AccessTokenProvider
{
    /**
     * Resolve a Microsoft Graph access token from disk configuration.
     *
     * @param  array<string, mixed>  $config
     */
    public function tokenFor(array $config): string;

    /**
     * Resolve a Microsoft Graph access token for a named filesystem disk.
     */
    public function tokenForDisk(string $disk): string;
}

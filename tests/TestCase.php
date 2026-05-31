<?php

declare(strict_types=1);

namespace SahabLibya\SharePointFilesystem\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use SahabLibya\SharePointFilesystem\SharePointFilesystemServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            SharePointFilesystemServiceProvider::class,
        ];
    }
}

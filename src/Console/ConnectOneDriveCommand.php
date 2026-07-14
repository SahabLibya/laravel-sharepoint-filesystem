<?php

declare(strict_types=1);

namespace SahabLibya\SharePointFilesystem\Console;

use Illuminate\Console\Command;
use RuntimeException;
use SahabLibya\SharePointFilesystem\Authentication\DelegatedTokenStore;
use SahabLibya\SharePointFilesystem\Authentication\DeviceCodeAuthenticator;
use SahabLibya\SharePointFilesystem\Authentication\MicrosoftIdentityConfiguration;
use Throwable;

final class ConnectOneDriveCommand extends Command
{
    protected $signature = 'onedrive:connect
        {disk=onedrive : Filesystem disk configured for OneDrive device-code authentication}';

    protected $description = 'Connect a personal or business OneDrive account to a filesystem disk';

    public function handle(DeviceCodeAuthenticator $authenticator, DelegatedTokenStore $tokens): int
    {
        $disk = (string) $this->argument('disk');
        $config = config("filesystems.disks.{$disk}");

        if (! is_array($config)) {
            $this->error("Filesystem disk [{$disk}] is not configured.");

            return self::FAILURE;
        }

        if (($config['auth_mode'] ?? 'client_credentials') !== 'device_code') {
            $this->error(
                "Filesystem disk [{$disk}] must set auth_mode to device_code before it can be connected."
            );

            return self::FAILURE;
        }

        try {
            $authorization = $authenticator->requestAuthorization($config);

            $this->newLine();
            $this->line((string) ($authorization['message'] ?? sprintf(
                'Open %s and enter code %s.',
                $authorization['verification_uri'],
                $authorization['user_code'],
            )));
            $this->newLine();
            $this->line('Waiting for Microsoft sign-in...');

            $tokenSet = $authenticator->waitForToken($config, $authorization);
            $tokens->put(MicrosoftIdentityConfiguration::tokenKey($config), $tokenSet);
        } catch (Throwable $exception) {
            $message = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'Unable to connect OneDrive.';

            $this->error($message);

            return self::FAILURE;
        }

        $this->info("OneDrive connected successfully for filesystem disk [{$disk}].");

        return self::SUCCESS;
    }
}

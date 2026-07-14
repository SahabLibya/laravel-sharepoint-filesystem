<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SharePoint/OneDrive Filesystem Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration is used for SharePoint/OneDrive filesystem integration
    | using Microsoft Graph API with application or delegated authentication.
    |
    */

    'token_storage_path' => env('ONEDRIVE_TOKEN_STORAGE_PATH'),

    'disks' => [
        'sharepoint' => [
            'driver' => 'sharepoint',
            'auth_mode' => 'client_credentials',
            'client_id' => env('GRAPH_CLIENT_ID'),
            'client_secret' => env('GRAPH_CLIENT_SECRET'),
            'tenant_id' => env('GRAPH_TENANT_ID', 'common'),
            'drive_id' => env('SHAREPOINT_DRIVE_ID'),
            'prefix' => env('SHAREPOINT_PREFIX', ''),
            'copy_monitor_timeout' => env('SHAREPOINT_COPY_MONITOR_TIMEOUT', 300),
            'copy_monitor_interval_ms' => env('SHAREPOINT_COPY_MONITOR_INTERVAL_MS', 1000),
            'throw' => false,
        ],

        'onedrive' => [
            'driver' => 'sharepoint',
            'auth_mode' => env('ONEDRIVE_AUTH_MODE', 'client_credentials'),
            'client_id' => env('ONEDRIVE_CLIENT_ID', env('GRAPH_CLIENT_ID')),
            'client_secret' => env('GRAPH_CLIENT_SECRET'),
            'tenant_id' => env('ONEDRIVE_TENANT_ID', env('GRAPH_TENANT_ID', 'common')),
            'drive_id' => env('ONEDRIVE_DRIVE_ID', env('SHAREPOINT_DRIVE_ID')),
            'prefix' => env('ONEDRIVE_PREFIX', ''),
            'token_key' => env('ONEDRIVE_TOKEN_KEY', 'default'),
            'scopes' => array_values(array_filter(explode(
                ' ',
                (string) env(
                    'ONEDRIVE_SCOPES',
                    'offline_access https://graph.microsoft.com/Files.ReadWrite'
                )
            ))),
            'copy_monitor_timeout' => env('ONEDRIVE_COPY_MONITOR_TIMEOUT', 300),
            'copy_monitor_interval_ms' => env('ONEDRIVE_COPY_MONITOR_INTERVAL_MS', 1000),
            'throw' => false,
        ],
    ],

];

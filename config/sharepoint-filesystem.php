<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SharePoint/OneDrive Filesystem Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration is used for SharePoint/OneDrive filesystem integration
    | using Microsoft Graph API with client credentials authentication.
    |
    */

    'disks' => [
        'sharepoint' => [
            'driver' => 'sharepoint',
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
            'client_id' => env('GRAPH_CLIENT_ID'),
            'client_secret' => env('GRAPH_CLIENT_SECRET'),
            'tenant_id' => env('GRAPH_TENANT_ID', 'common'),
            'drive_id' => env('SHAREPOINT_DRIVE_ID'),
            'prefix' => env('ONEDRIVE_PREFIX', ''),
            'copy_monitor_timeout' => env('ONEDRIVE_COPY_MONITOR_TIMEOUT', 300),
            'copy_monitor_interval_ms' => env('ONEDRIVE_COPY_MONITOR_INTERVAL_MS', 1000),
            'throw' => false,
        ],
    ],

];

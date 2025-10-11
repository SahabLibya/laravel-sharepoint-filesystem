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
            'throw' => false,
        ],

        'onedrive' => [
            'driver' => 'sharepoint',
            'client_id' => env('GRAPH_CLIENT_ID'),
            'client_secret' => env('GRAPH_CLIENT_SECRET'),
            'tenant_id' => env('GRAPH_TENANT_ID', 'common'),
            'drive_id' => env('SHAREPOINT_DRIVE_ID'),
            'prefix' => env('ONEDRIVE_PREFIX', ''),
            'throw' => false,
        ],
    ],

];


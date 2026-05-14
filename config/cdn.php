<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CDN Base URL / Endpoint URL
    |--------------------------------------------------------------------------
    | The root URL of your CDN backend (no trailing slash).
    | Can be specified as 'url' or 'endpoint_url' (ImageKit compatibility).
    | Example: https://cdn.example.com
    */
    'url' => env('CDN_URL', env('CDN_ENDPOINT_URL')),
    'endpoint_url' => env('CDN_ENDPOINT_URL', env('CDN_URL')), // ImageKit compatibility

    /*
    |--------------------------------------------------------------------------
    | API Key
    |--------------------------------------------------------------------------
    | A personal access token generated in the CDN dashboard.
    | This token must have the "api" ability enabled.
    */
    'api_key' => env('CDN_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Default Disk Options
    |--------------------------------------------------------------------------
    | These values are used when registering the "cdn" disk automatically.
    | You can override them per-disk inside config/filesystems.php.
    */
    'disks' => [
        'cdn' => [
            'driver'         => 'cdn',
            'url'            => env('CDN_URL'),
            'api_key'        => env('CDN_API_KEY'),
            'default_folder' => env('CDN_DEFAULT_FOLDER', ''),
            'visibility'     => 'public',
        ],
    ],

];

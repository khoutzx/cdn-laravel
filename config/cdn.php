<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CDN Base URL
    |--------------------------------------------------------------------------
    | The root URL of your CDN backend (no trailing slash).
    | Example: https://cdn.example.com
    */
    'url' => env('CDN_URL'),

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

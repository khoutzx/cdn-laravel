<?php

namespace Cdn\LaravelSdk;

use Cdn\LaravelSdk\Adapters\NapiCdnAdapter;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;

class CdnServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/cdn.php', 'cdn');

        // Register NapiCdnAdapter as singleton
        $this->app->singleton('napi-cdn', function ($app) {
            return new NapiCdnAdapter('cdn');
        });

        // Register alias for easier access
        $this->app->alias('napi-cdn', NapiCdnAdapter::class);
    }

    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../config/cdn.php' => config_path('cdn.php'),
        ], 'cdn-config');

        // Register the "cdn" filesystem driver
        Storage::extend('cdn', function ($app, array $config) {
            // Support both 'url' and 'endpoint_url' for ImageKit compatibility
            $url    = $config['url'] ?? $config['endpoint_url'] ?? config('cdn.url') ?? config('cdn.endpoint_url') ?? throw new \InvalidArgumentException('CDN SDK: "url" or "endpoint_url" is required in disk config or CDN_URL env.');
            $apiKey = $config['api_key'] ?? config('cdn.api_key') ?? throw new \InvalidArgumentException('CDN SDK: "api_key" is required in disk config or CDN_API_KEY env.');
            $folder = $config['default_folder'] ?? '';

            $client      = new CdnClient($url, $apiKey);
            $cdnAdapter  = new CdnFilesystemAdapter($client, $url, $folder);
            $flysystem   = new Filesystem($cdnAdapter);

            return new FilesystemAdapter($flysystem, $cdnAdapter, $config);
        });
    }
}

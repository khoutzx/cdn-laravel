<?php

namespace Napi\Cdn;

use Napi\Cdn\Adapters\NapiCdnAdapter;
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

            $client      = new Client($url, $apiKey);
            $cdnAdapter  = new CdnFilesystemAdapter($client, $url, $folder);
            $flysystem   = new Filesystem($cdnAdapter);

            return new class($flysystem, $cdnAdapter, $config) extends FilesystemAdapter {
                public function __call($method, $args) {
                    return parent::__call($method, $args);
                }
                private $cdnAdapter;

                public function __construct($flysystem, $cdnAdapter, $config) {
                    parent::__construct($flysystem, $cdnAdapter, $config);
                    $this->cdnAdapter = $cdnAdapter;
                }

                public function url($path) {
                    return $this->cdnAdapter->publicUrl($path, new \League\Flysystem\Config());
                }

                public function put($path, $contents, $options = []) {
                    // Verificar se é resource ou string
                    if (is_resource($contents)) {
                        $this->driver->writeStream($path, $contents, $options);
                    } else {
                        $this->driver->write($path, $contents, $options);
                    }

                    // Pegar o arquivo real que foi salvo (com timestamp)
                    if ($this->cdnAdapter->lastUploadedFile && isset($this->cdnAdapter->lastUploadedFile['url'])) {
                        return $this->cdnAdapter->lastUploadedFile['url']; // Retornar URL completa!
                    }

                    return $path; // Fallback para path original
                }

                public function putFile($path, $file = null, $options = []) {
                    $result = parent::putFile($path, $file, $options);

                    // Pegar URL real se disponível
                    if ($this->cdnAdapter->lastUploadedFile && isset($this->cdnAdapter->lastUploadedFile['url'])) {
                        return $this->cdnAdapter->lastUploadedFile['url'];
                    }

                    return $result ?: $path;
                }

                public function putFileAs($path, $file = null, $name = null, $options = []) {
                    $result = parent::putFileAs($path, $file, $name, $options);

                    // Pegar URL real se disponível
                    if ($this->cdnAdapter->lastUploadedFile && isset($this->cdnAdapter->lastUploadedFile['url'])) {
                        return $this->cdnAdapter->lastUploadedFile['url'];
                    }

                    return $result ?: $path . '/' . $name;
                }
            };
        });
    }
}

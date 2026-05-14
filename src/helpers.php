<?php

if (!function_exists('napi_cdn')) {
    /**
     * Global Napi CDN helper - Use like ImageKit
     *
     * @return \Cdn\LaravelSdk\Adapters\NapiCdnAdapter
     */
    function napi_cdn(): \Cdn\LaravelSdk\Adapters\NapiCdnAdapter
    {
        return app('napi-cdn');
    }
}

if (!function_exists('cdn')) {
    /**
     * Alias for napi_cdn() - Short and sweet
     *
     * @return \Cdn\LaravelSdk\Adapters\NapiCdnAdapter
     */
    function cdn(): \Cdn\LaravelSdk\Adapters\NapiCdnAdapter
    {
        return napi_cdn();
    }
}
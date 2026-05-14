<?php

if (!function_exists('napi_cdn')) {
    /**
     * Global Napi CDN helper - Use like ImageKit
     *
     * @return \Napi\Cdn\Adapters\NapiCdnAdapter
     */
    function napi_cdn(): \Napi\Cdn\Adapters\NapiCdnAdapter
    {
        return app('napi-cdn');
    }
}

if (!function_exists('cdn')) {
    /**
     * Alias for napi_cdn() - Short and sweet
     *
     * @return \Napi\Cdn\Adapters\NapiCdnAdapter
     */
    function cdn(): \Napi\Cdn\Adapters\NapiCdnAdapter
    {
        return napi_cdn();
    }
}
<?php

namespace Cdn\LaravelSdk\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Napi CDN Facade - Use like: NapiCdn::upload($file, 'name.jpg', 'folder')
 *
 * @method static string upload($file, string $filename, string $folder = '')
 * @method static string url($options)
 * @method static bool exists(string $path)
 * @method static bool delete(string $path)
 * @method static array listFiles(string $folder = '')
 * @method static int getFileSize(string $path)
 * @method static string download(string $path)
 * @method static bool move(string $from, string $to)
 * @method static bool copy(string $from, string $to)
 */
class NapiCdn extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'napi-cdn';
    }
}
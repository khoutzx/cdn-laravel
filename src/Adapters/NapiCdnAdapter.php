<?php

namespace Cdn\LaravelSdk\Adapters;

use Illuminate\Support\Facades\Storage;

/**
 * Napi CDN Adapter - ImageKit-like interface for Napi CDN
 *
 * Simple, clean interface that works exactly like ImageKit but with
 * the power of Laravel Storage and Flysystem integration.
 *
 * Usage:
 * $napi = new NapiCdnAdapter();
 * $url = $napi->upload($file, 'filename.jpg', 'folder');
 * $url = $napi->url('folder/filename.jpg');
 */
class NapiCdnAdapter
{
    private $disk;

    public function __construct(string $diskName = 'cdn')
    {
        $this->disk = Storage::disk($diskName);
    }

    /**
     * Upload a file to Napi CDN - ImageKit-like interface
     *
     * @param mixed $file - File path, UploadedFile, or file contents
     * @param string $filename - Desired filename
     * @param string $folder - Folder to store in
     * @return string - Full CDN URL
     */
    public function upload($file, string $filename, string $folder = ''): string
    {
        $path = $folder ? $folder . '/' . $filename : $filename;

        if (is_string($file) && file_exists($file)) {
            // Local file path
            $contents = file_get_contents($file);
        } elseif (is_object($file) && method_exists($file, 'getContent')) {
            // UploadedFile
            $contents = $file->getContent();
        } elseif (is_object($file) && method_exists($file, 'get')) {
            // File object with get() method
            $contents = $file->get();
        } else {
            // Direct contents
            $contents = $file;
        }

        $this->disk->put($path, $contents);
        return $this->url($path);
    }

    /**
     * Get CDN URL for a file path - ImageKit-like interface
     *
     * @param array|string $options - Path string or options array
     * @return string - Full CDN URL
     */
    public function url($options): string
    {
        if (is_array($options)) {
            // ImageKit-style options array
            $path = $options['path'] ?? $options['src'] ?? '';
        } else {
            // Direct path string
            $path = $options;
        }

        return $this->disk->url($path);
    }

    /**
     * Check if file exists
     *
     * @param string $path - File path
     * @return bool
     */
    public function exists(string $path): bool
    {
        return $this->disk->exists($path);
    }

    /**
     * Delete a file
     *
     * @param string $path - File path
     * @return bool
     */
    public function delete(string $path): bool
    {
        return $this->disk->delete($path);
    }

    /**
     * List files in a folder
     *
     * @param string $folder - Folder path
     * @return array
     */
    public function listFiles(string $folder = ''): array
    {
        return $this->disk->files($folder);
    }

    /**
     * Get file size
     *
     * @param string $path - File path
     * @return int - File size in bytes
     */
    public function getFileSize(string $path): int
    {
        return $this->disk->size($path);
    }

    /**
     * Download file contents
     *
     * @param string $path - File path
     * @return string - File contents
     */
    public function download(string $path): string
    {
        return $this->disk->get($path);
    }

    /**
     * Move/rename a file
     *
     * @param string $from - Source path
     * @param string $to - Destination path
     * @return bool
     */
    public function move(string $from, string $to): bool
    {
        return $this->disk->move($from, $to);
    }

    /**
     * Copy a file
     *
     * @param string $from - Source path
     * @param string $to - Destination path
     * @return bool
     */
    public function copy(string $from, string $to): bool
    {
        return $this->disk->copy($from, $to);
    }
}
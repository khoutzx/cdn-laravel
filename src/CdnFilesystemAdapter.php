<?php

namespace Napi\Cdn;

use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCheckDirectoryExistence;
use League\Flysystem\UnableToCheckFileExistence;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\UrlGeneration\PublicUrlGenerator;
use RuntimeException;

/**
 * Flysystem 3 adapter for the CDN backend.
 *
 * Paths follow standard filesystem conventions: "folder/sub/file.jpg"
 * The adapter maps them to the CDN's folder/name structure transparently.
 */
class CdnFilesystemAdapter implements FilesystemAdapter, PublicUrlGenerator
{
    private Client $client;
    private string $cdnUrl;
    private string $defaultFolder;

    public function __construct(Client $client, string $cdnUrl, string $defaultFolder = '')
    {
        $this->client        = $client;
        $this->cdnUrl        = rtrim($cdnUrl, '/');
        $this->defaultFolder = trim($defaultFolder, '/');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Write
    // ─────────────────────────────────────────────────────────────────────────

    public function write(string $path, string $contents, Config $config): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cdn_sdk_');

        try {
            file_put_contents($tmp, $contents);
            [$folder, $filename] = $this->splitPath($path);
            $this->client->upload($tmp, $filename, $folder);
        } catch (RuntimeException $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        } finally {
            @unlink($tmp);
        }
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        try {
            [$folder, $filename] = $this->splitPath($path);
            $this->client->uploadStream($contents, $filename, $folder);
        } catch (RuntimeException $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Read
    // ─────────────────────────────────────────────────────────────────────────

    public function read(string $path): string
    {
        try {
            $file = $this->requireFile($path);
            return $this->client->downloadFile($file['id']);
        } catch (RuntimeException $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    public function readStream(string $path)
    {
        try {
            $file = $this->requireFile($path);
            return $this->client->downloadFileStream($file['id']);
        } catch (RuntimeException $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Existence
    // ─────────────────────────────────────────────────────────────────────────

    public function fileExists(string $path): bool
    {
        try {
            return $this->client->resolveFile($this->prefixed($path)) !== null;
        } catch (RuntimeException $e) {
            throw UnableToCheckFileExistence::forLocation($path, $e);
        }
    }

    public function directoryExists(string $path): bool
    {
        try {
            return $this->client->resolveFolder($this->prefixed($path)) !== null;
        } catch (RuntimeException $e) {
            throw UnableToCheckDirectoryExistence::forLocation($path, $e);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Delete
    // ─────────────────────────────────────────────────────────────────────────

    public function delete(string $path): void
    {
        try {
            $file = $this->client->resolveFile($this->prefixed($path));
            if ($file === null) {
                return; // already gone — Flysystem contract allows silently skipping
            }
            $this->client->deleteFiles([$file['id']]);
        } catch (RuntimeException $e) {
            throw UnableToDeleteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function deleteDirectory(string $path): void
    {
        try {
            $folder = $this->client->resolveFolder($this->prefixed($path));
            if ($folder === null) {
                return;
            }
            $this->client->deleteFolder($folder['id']);
        } catch (RuntimeException $e) {
            throw UnableToDeleteDirectory::atLocation($path, $e->getMessage(), $e);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Directories
    // ─────────────────────────────────────────────────────────────────────────

    public function createDirectory(string $path, Config $config): void
    {
        try {
            $this->client->createDirectoryByPath($this->prefixed($path));
        } catch (RuntimeException $e) {
            throw UnableToCreateDirectory::atLocation($path, $e->getMessage(), $e);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Visibility
    // ─────────────────────────────────────────────────────────────────────────

    public function setVisibility(string $path, string $visibility): void
    {
        try {
            $file = $this->requireFile($path);
            $this->client->setFileVisibility($file['id'], $visibility);
        } catch (RuntimeException $e) {
            throw UnableToSetVisibility::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function visibility(string $path): FileAttributes
    {
        return $this->fetchFileAttributes($path, StorageAttributes::ATTRIBUTE_VISIBILITY);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Metadata
    // ─────────────────────────────────────────────────────────────────────────

    public function mimeType(string $path): FileAttributes
    {
        return $this->fetchFileAttributes($path, StorageAttributes::ATTRIBUTE_MIME_TYPE);
    }

    public function lastModified(string $path): FileAttributes
    {
        return $this->fetchFileAttributes($path, StorageAttributes::ATTRIBUTE_LAST_MODIFIED);
    }

    public function fileSize(string $path): FileAttributes
    {
        return $this->fetchFileAttributes($path, StorageAttributes::ATTRIBUTE_FILE_SIZE);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // List
    // ─────────────────────────────────────────────────────────────────────────

    public function listContents(string $path, bool $deep): iterable
    {
        try {
            // Resolve the folder (or root)
            $folderId = null;
            if ($path !== '' && $path !== '/') {
                $prefixed = $this->prefixed($path);
                $folder   = $this->client->resolveFolder($prefixed);
                if ($folder === null) {
                    return;
                }
                $folderId = $folder['id'];
            }

            yield from $this->listRecursive($path, $folderId, $deep);
        } catch (RuntimeException $e) {
            // Return empty iterator on error (Flysystem contract)
            return;
        }
    }

    private function listRecursive(string $prefix, ?string $folderId, bool $deep): iterable
    {
        $contents = $this->client->listFolder($folderId);

        foreach ($contents['files'] ?? [] as $file) {
            $entryPath = ltrim(($prefix !== '' ? $prefix . '/' : '') . $file['name'], '/');
            yield new FileAttributes(
                path:             $entryPath,
                fileSize:         $file['size']       ?? null,
                visibility:       ($file['is_private'] ?? false) ? 'private' : 'public',
                lastModified:     isset($file['updated_at'])
                                    ? strtotime($file['updated_at'])
                                    : null,
                mimeType:         null, // not in list payload
                extraMetadata:    ['id' => $file['id'], 'url' => $file['url'] ?? null],
            );
        }

        foreach ($contents['folders'] ?? [] as $sub) {
            $dirPath = ltrim(($prefix !== '' ? $prefix . '/' : '') . $sub['name'], '/');
            yield new DirectoryAttributes(
                path:          $dirPath,
                visibility:    null,
                lastModified:  null,
                extraMetadata: ['id' => $sub['id']],
            );

            if ($deep) {
                yield from $this->listRecursive($dirPath, $sub['id'], true);
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Move & Copy
    // ─────────────────────────────────────────────────────────────────────────

    public function move(string $source, string $destination, Config $config): void
    {
        try {
            // 1. Upload to destination
            $contents = $this->read($source);
            $this->write($destination, $contents, $config);

            // 2. Delete source
            $this->delete($source);
        } catch (RuntimeException $e) {
            throw UnableToMoveFile::fromLocationTo($source, $destination, $e);
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $contents = $this->read($source);
            $this->write($destination, $contents, $config);
        } catch (RuntimeException $e) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $e);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // URL generation  (implements PublicUrlGenerator)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Returns the public CDN URL for the given path.
     *
     * URL format: {cdn_url}/{user_id}/file/{path}
     *
     * This matches the backend's File::getUrlAttribute() exactly.
     */
    public function publicUrl(string $path, Config $config): string
    {
        $userId = $this->client->getUserId();
        $full   = $this->prefixed($path);

        return $this->cdnUrl . '/' . $userId . '/file/' . ltrim($full, '/');
    }

    /**
     * Generate a URL for the file at the given path.
     * Required by UrlGenerator interface.
     */
    public function url(string $path, Config $config): string
    {
        return $this->publicUrl($path, $config);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internals
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Prepend the default_folder prefix to a path, if configured.
     * "photo.jpg"        → "uploads/photo.jpg"    (when default_folder = "uploads")
     * "2024/photo.jpg"   → "uploads/2024/photo.jpg"
     */
    private function prefixed(string $path): string
    {
        $path = ltrim($path, '/');

        if ($this->defaultFolder === '') {
            return $path;
        }

        // Avoid double-prefixing if the path already starts with the folder
        if (str_starts_with($path, $this->defaultFolder . '/') || $path === $this->defaultFolder) {
            return $path;
        }

        return $this->defaultFolder . '/' . $path;
    }

    /**
     * Split "folder/sub/file.jpg" into ["folder/sub", "file.jpg"].
     * Applies the defaultFolder prefix first.
     */
    private function splitPath(string $path): array
    {
        $path     = $this->prefixed($path);
        $segments = explode('/', trim($path, '/'));
        $filename = array_pop($segments);
        $folder   = implode('/', $segments);

        return [$folder, $filename];
    }

    /**
     * Resolve a file or throw UnableToReadFile.
     */
    private function requireFile(string $path): array
    {
        $file = $this->client->resolveFile($this->prefixed($path));

        if ($file === null) {
            throw new RuntimeException("File not found at path: {$path}");
        }

        return $file;
    }

    /**
     * Fetch a FileAttributes object for the given path and attribute.
     */
    private function fetchFileAttributes(string $path, string $attribute): FileAttributes
    {
        try {
            $file = $this->requireFile($path);

            return new FileAttributes(
                path:          $path,
                fileSize:      $file['size']       ?? null,
                visibility:    ($file['is_private'] ?? false) ? 'private' : 'public',
                lastModified:  isset($file['updated_at']) ? strtotime($file['updated_at']) : null,
                mimeType:      null, // the CDN API doesn't store MIME — derive from extension if needed
                extraMetadata: ['id' => $file['id'], 'url' => $file['url'] ?? null],
            );
        } catch (RuntimeException $e) {
            throw UnableToRetrieveMetadata::$attribute($path, $e->getMessage(), $e);
        }
    }
}

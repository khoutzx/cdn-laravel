<?php

namespace Napi\Cdn;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

/**
 * Thin HTTP client that wraps all CDN API calls.
 * All methods throw RuntimeException on unrecoverable errors.
 */
class Client
{
    private ?HttpClient $http = null;
    private ?string $baseUrl = null;
    private ?string $apiKey = null;

    /** Cached user ID resolved from GET /api/user */
    private ?string $userId = null;

    public function __construct(string $baseUrl, string $apiKey)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey  = $apiKey;

        $this->http = new HttpClient([
            'base_uri' => $this->baseUrl,
            'headers'  => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept'        => 'application/json',
            ],
            'http_errors' => false, // we handle status codes ourselves
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // User
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Returns the authenticated user's ID.
     * Result is cached for the lifetime of this object.
     */
    public function getUserId(): string
    {
        if ($this->userId !== null) {
            return $this->userId;
        }

        // Ensure all properties are initialized
        $this->ensureInitialized();

        $response = $this->http->get('/api/user');
        $body     = $this->decode($response, '/api/user');

        $this->userId = $body['data']['id'] ?? throw new RuntimeException('CDN SDK: unable to resolve user ID from /api/user');

        return $this->userId;
    }

    /**
     * Ensure all required properties are initialized.
     * This prevents typed property errors in certain Laravel contexts.
     */
    private function ensureInitialized(): void
    {
        if ($this->baseUrl === null || $this->apiKey === null || $this->http === null) {
            throw new RuntimeException('CDN SDK: CdnClient not properly initialized. Ensure constructor was called.');
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Files
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Upload a file from a local path.
     *
     * @return array The file resource returned by the CDN API.
     */
    public function upload(string $localPath, string $filename, string $folder = ''): array
    {
        $multipart = [
            [
                'name'     => 'file',
                'contents' => fopen($localPath, 'r'),
                'filename' => $filename,
            ],
        ];

        if ($folder !== '') {
            $multipart[] = [
                'name'     => 'folder',
                'contents' => ltrim($folder, '/'),
            ];
        }

        $response = $this->http->post('/upload', [
            'multipart' => $multipart,
        ]);

        return $this->decode($response, '/upload');
    }

    /**
     * Upload from an open stream resource.
     *
     * @param resource $stream
     * @return array
     */
    public function uploadStream($stream, string $filename, string $folder = ''): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cdn_sdk_');
        file_put_contents($tmp, $stream);

        try {
            return $this->upload($tmp, $filename, $folder);
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * Resolve a file by its logical path (folder/filename).
     * Returns null if the file does not exist.
     */
    public function resolveFile(string $path): ?array
    {
        $response = $this->http->get('/api/file/resolve', [
            'query' => ['path' => ltrim($path, '/')],
        ]);

        if ($response->getStatusCode() === 404) {
            return null;
        }

        return $this->decode($response, '/api/file/resolve')['data'] ?? null;
    }

    /**
     * Get a file's metadata by its ULID.
     */
    public function getFile(string $fileId): ?array
    {
        $response = $this->http->get("/api/file/{$fileId}");

        if ($response->getStatusCode() === 404) {
            return null;
        }

        return $this->decode($response, "/api/file/{$fileId}")['data'] ?? null;
    }

    /**
     * Download the raw binary content of a file.
     */
    public function downloadFile(string $fileId): string
    {
        $response = $this->http->get("/api/file/{$fileId}/download");

        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException("CDN SDK: download failed for file {$fileId} (HTTP {$response->getStatusCode()})");
        }

        return (string) $response->getBody();
    }

    /**
     * Download file content as an open stream resource.
     *
     * @return resource
     */
    public function downloadFileStream(string $fileId)
    {
        $contents = $this->downloadFile($fileId);
        $stream   = fopen('php://temp', 'r+');
        fwrite($stream, $contents);
        rewind($stream);

        return $stream;
    }

    /**
     * Set the visibility of a file.
     *
     * @param string $visibility "public" or "private"
     */
    public function setFileVisibility(string $fileId, string $visibility): void
    {
        $response = $this->http->patch("/api/file/{$fileId}/visibility", [
            'json' => ['visibility' => $visibility],
        ]);

        $this->assertSuccess($response, "/api/file/{$fileId}/visibility");
    }

    /**
     * Rename a file.
     */
    public function renameFile(string $fileId, string $newName): void
    {
        $response = $this->http->post("/api/file/{$fileId}/rename", [
            'json' => ['name' => $newName],
        ]);

        $this->assertSuccess($response, "/api/file/{$fileId}/rename");
    }

    /**
     * Delete one or more files by ULID.
     *
     * @param string[] $fileIds
     */
    public function deleteFiles(array $fileIds): void
    {
        $response = $this->http->delete('/api/files/delete', [
            'json' => ['files' => implode(',', $fileIds)],
        ]);

        $this->assertSuccess($response, '/api/files/delete');
    }

    /**
     * Move files to a target folder (by folder ULID).
     *
     * @param string[] $fileIds
     */
    public function moveFiles(array $fileIds, string $targetFolderId): void
    {
        $response = $this->http->post('/api/files/move', [
            'json' => [
                'files'         => implode(',', $fileIds),
                'target_folder' => $targetFolderId,
            ],
        ]);

        $this->assertSuccess($response, '/api/files/move');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Folders
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Resolve a folder by its logical path.
     * Returns null if the folder does not exist (does NOT create it).
     */
    public function resolveFolder(string $path): ?array
    {
        $response = $this->http->get('/api/folder/resolve', [
            'query' => ['path' => ltrim($path, '/')],
        ]);

        if ($response->getStatusCode() === 404) {
            return null;
        }

        return $this->decode($response, '/api/folder/resolve')['data'] ?? null;
    }

    /**
     * List the contents of a folder (files + sub-folders) by ULID.
     * Pass null for the root folder.
     */
    public function listFolder(?string $folderId = null): array
    {
        $url      = $folderId ? "/api/folder/{$folderId}" : '/api/folder';
        $response = $this->http->get($url);

        return $this->decode($response, $url)['data'] ?? [];
    }

    /**
     * Create a folder at the given path, creating parents as needed.
     * Returns the deepest folder's data array.
     */
    public function createDirectoryByPath(string $path): array
    {
        $parts    = array_filter(explode('/', trim($path, '/')));
        $parentId = null;

        foreach ($parts as $part) {
            $response = $this->http->post('/api/folder', [
                'json' => array_filter([
                    'name'      => $part,
                    'parent_id' => $parentId,
                ]),
            ]);

            // 200 means it already existed (firstOrCreate-like behaviour) — handle both
            $body      = $this->decode($response, '/api/folder');
            $parentId  = $body['data']['id']
                ?? throw new RuntimeException("CDN SDK: could not create folder '{$part}'");
        }

        return $this->listFolder($parentId);
    }

    /**
     * Delete a folder by ULID (recursive — backend handles cascading).
     */
    public function deleteFolder(string $folderId): void
    {
        $response = $this->http->delete("/api/folder/{$folderId}");
        $this->assertSuccess($response, "/api/folder/{$folderId}");
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function decode($response, string $endpoint): array
    {
        $status = $response->getStatusCode();
        $body   = json_decode((string) $response->getBody(), true) ?? [];

        if ($status >= 400) {
            $message = $body['message'] ?? $body['error'] ?? "HTTP {$status}";
            throw new RuntimeException("CDN SDK: request to {$endpoint} failed — {$message}");
        }

        return $body;
    }

    private function assertSuccess($response, string $endpoint): void
    {
        $this->decode($response, $endpoint);
    }
}

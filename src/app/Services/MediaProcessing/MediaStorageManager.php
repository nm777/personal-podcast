<?php

namespace App\Services\MediaProcessing;

use Illuminate\Support\Facades\Storage;

class MediaStorageManager
{
    /**
     * Store file content to permanent location using hash-based naming.
     */
    public function storeFile(string $content, string $extension, ?string $sourceUrl = null): array
    {
        $fileHash = hash('sha256', $content);
        $finalPath = 'media/'.$fileHash.'.'.$extension;

        // Store the file
        Storage::disk('public')->put($finalPath, $content);

        return [
            'file_path' => $finalPath,
            'file_hash' => $fileHash,
            'filesize' => strlen($content),
            'source_url' => $sourceUrl,
        ];
    }

    /**
     * Move temporary file to permanent location.
     */
    public function moveTempFile(string $tempPath, ?string $sourceUrl = null): array
    {
        $fullPath = Storage::disk('public')->path($tempPath);
        $content = file_get_contents($fullPath);
        $extension = pathinfo($fullPath, PATHINFO_EXTENSION);

        return $this->storeFile($content, $extension, $sourceUrl);
    }

    /**
     * Clean up temporary file.
     */
    public function cleanupTempFile(string $tempPath): void
    {
        Storage::disk('public')->delete($tempPath);
    }

    /**
     * Get file size from storage.
     */
    public function getFileSize(string $filePath): int
    {
        $fullPath = Storage::disk('public')->path($filePath);

        if (! file_exists($fullPath)) {
            return 0;
        }

        return filesize($fullPath);
    }

    /**
     * Check if file exists in storage.
     */
    public function fileExists(string $filePath): bool
    {
        return Storage::disk('public')->exists($filePath);
    }
}

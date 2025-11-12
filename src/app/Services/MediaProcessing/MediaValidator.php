<?php

namespace App\Services\MediaProcessing;

use Illuminate\Support\Facades\File;

class MediaValidator
{
    /**
     * Validate media file content and return metadata.
     */
    public function validate(string $filePath, ?string $content = null): array
    {
        if ($content === null) {
            $content = file_get_contents($filePath);
        }

        $this->validateMediaContent($content);

        return [
            'mime_type' => $this->detectMimeType($filePath, $content),
            'filesize' => strlen($content),
            'is_valid' => true,
        ];
    }

    /**
     * Validate that content is valid media.
     */
    private function validateMediaContent(string $content): void
    {
        $validMediaSignatures = [
            'RIFF' => true, // WAV/AVI
            'OggS' => true, // OGG
            'fLaC' => true, // FLAC
            'MP4' => true,  // M4A/MP4
            "\xFF\xFB" => true, // MP3
            "\xFF\xF3" => true, // MP3
            "\xFF\xF2" => true, // MP3
        ];

        $fileSignature = substr($content, 0, 4);
        $isValidMedia = isset($validMediaSignatures[$fileSignature]) ||
                       isset($validMediaSignatures[substr($content, 0, 2)]) ||
                       str_starts_with($fileSignature, 'ID3'); // MP3 with ID3 tag

        if (! $isValidMedia && strlen($content) > 100) {
            throw new \InvalidArgumentException('Content does not appear to be a valid audio file');
        }
    }

    /**
     * Detect MIME type for media file.
     */
    private function detectMimeType(string $filePath, string $content): string
    {
        // Try to get MIME type from file system first
        if (file_exists($filePath)) {
            $mimeType = File::mimeType($filePath);
            if ($mimeType && $mimeType !== 'text/plain') {
                return $mimeType;
            }
        }

        // Fallback to content-based detection
        return $this->detectMimeTypeFromContent($content);
    }

    /**
     * Detect MIME type from content signature.
     */
    private function detectMimeTypeFromContent(string $content): string
    {
        $signatures = [
            'RIFF' => 'audio/wav',
            'OggS' => 'audio/ogg',
            'fLaC' => 'audio/flac',
            'MP4' => 'audio/mp4',
            "\xFF\xFB" => 'audio/mpeg',
            "\xFF\xF3" => 'audio/mpeg',
            "\xFF\xF2" => 'audio/mpeg',
            'ID3' => 'audio/mpeg',
        ];

        foreach ($signatures as $signature => $mimeType) {
            if (str_starts_with($content, $signature)) {
                return $mimeType;
            }
        }

        // Default fallback
        return 'application/octet-stream';
    }
}

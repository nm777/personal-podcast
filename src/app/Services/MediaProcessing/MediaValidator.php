<?php

namespace App\Services\MediaProcessing;

use Illuminate\Support\Facades\File;

class MediaValidator
{
    /**
     * Validate media file and return metadata.
     * Only reads the file header for signature validation, not the entire file.
     */
    public function validate(string $filePath): array
    {
        $header = file_get_contents($filePath, false, null, 0, 4096);

        $this->validateMediaContent($header);

        return [
            'mime_type' => $this->detectMimeType($filePath, $header),
            'filesize' => file_exists($filePath) ? filesize($filePath) : 0,
            'is_valid' => true,
        ];
    }

    /**
     * Validate that content is valid media based on file signature.
     */
    private function validateMediaContent(string $header): void
    {
        $validMediaSignatures = [
            'RIFF' => true,
            'OggS' => true,
            'fLaC' => true,
            'MP4' => true,
            "\xFF\xFB" => true,
            "\xFF\xF3" => true,
            "\xFF\xF2" => true,
        ];

        $fileSignature = substr($header, 0, 4);
        $isValidMedia = isset($validMediaSignatures[$fileSignature]) ||
                       isset($validMediaSignatures[substr($header, 0, 2)]) ||
                       str_starts_with($fileSignature, 'ID3');

        if (! $isValidMedia && strlen($header) > 100) {
            throw new \InvalidArgumentException('Content does not appear to be a valid audio file');
        }
    }

    /**
     * Detect MIME type for media file.
     */
    private function detectMimeType(string $filePath, string $header): string
    {
        if (file_exists($filePath)) {
            $mimeType = File::mimeType($filePath);
            if ($mimeType && $mimeType !== 'text/plain') {
                return $mimeType;
            }
        }

        return $this->detectMimeTypeFromContent($header);
    }

    /**
     * Detect MIME type from content signature.
     */
    private function detectMimeTypeFromContent(string $header): string
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
            if (str_starts_with($header, $signature)) {
                return $mimeType;
            }
        }

        return 'application/octet-stream';
    }
}

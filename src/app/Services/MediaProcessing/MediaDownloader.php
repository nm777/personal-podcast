<?php

namespace App\Services\MediaProcessing;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MediaDownloader
{
    /**
     * Download media from URL with redirect handling.
     */
    public function downloadFromUrl(string $url): ?string
    {
        try {
            $response = Http::timeout(60)->withOptions([
                'allow_redirects' => [
                    'max' => 5,
                    'strict' => true,
                    'referer' => true,
                    'protocols' => ['http', 'https'],
                    'track_redirects' => true,
                ],
            ])->get($url);

            if (! $response->successful()) {
                throw new \Exception('Failed to download file: HTTP '.$response->status());
            }

            $contents = $response->body();

            if (empty($contents)) {
                throw new \Exception('Downloaded file is empty');
            }

            // Handle JavaScript redirect pages
            if ($this->isHtmlContent($contents)) {
                $redirectUrl = $this->extractRedirectUrl($contents, $url);

                if ($redirectUrl) {
                    try {
                        return $this->downloadFromUrl($redirectUrl);
                    } catch (\Exception $e) {
                        // If redirect fails, return the original HTML error
                        throw new \Exception('Download failed: Got HTML redirect page instead of media file');
                    }
                }

                throw new \Exception('Download failed: Got HTML content instead of media file');
            }

            // Validate media content
            $this->validateMediaContent($contents);

            return $contents;
        } catch (\Exception $e) {
            Log::error('Media download failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Check if content is HTML.
     */
    private function isHtmlContent(string $content): bool
    {
        return str_starts_with($content, '<!DOCTYPE html') || str_starts_with($content, '<html');
    }

    /**
     * Extract redirect URL from JavaScript.
     */
    private function extractRedirectUrl(string $html, string $originalUrl): ?string
    {
        // Pattern 1: window.location.replace('url')
        if (preg_match('/window\.location\.replace\([\'"]([^\'"]+)[\'"]\)/', $html, $matches)) {
            return $this->makeAbsoluteUrl($matches[1], $originalUrl);
        }

        // Pattern 2: window.location.href.replace('pattern', 'replacement')
        if (preg_match('/window\.location\.href\.replace\([\'"]([^\'"]+)[\'"],\s*[\'"]([^\'"]+)[\'"]\)/', $html, $matches)) {
            $pattern = $matches[1];
            $replacement = $matches[2];
            $redirectUrl = str_replace($pattern, $replacement, $originalUrl);

            return $this->makeAbsoluteUrl($redirectUrl, $originalUrl);
        }

        return null;
    }

    /**
     * Convert relative URL to absolute.
     */
    private function makeAbsoluteUrl(string $url, string $baseUrl): string
    {
        if (str_starts_with($url, 'http')) {
            return $url;
        }

        $parsedUrl = parse_url($baseUrl);
        $schemeHost = $parsedUrl['scheme'].'://'.$parsedUrl['host'];

        if (str_starts_with($url, '/')) {
            return $schemeHost.$url;
        }

        $path = dirname($parsedUrl['path']);

        return $schemeHost.$path.'/'.$url;
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
            throw new \Exception('Content does not appear to be a valid audio file');
        }
    }
}

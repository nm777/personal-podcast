<?php

namespace App\Services\MediaProcessing;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MediaDownloader
{
    /**
     * Download media from URL with redirect handling.
     */
    public function downloadFromUrl(string $url): ?string
    {
        $this->validateUrlSafety($url);

        try {
            $response = $this->executeDownload($url);
            $contents = $response->body();

            $this->validateResponse($response, $contents);
            $contents = $this->handleHtmlRedirect($contents, $url);
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
     * Execute HTTP download with redirect options.
     */
    private function executeDownload(string $url): Response
    {
        return Http::timeout(60)->withOptions([
            'allow_redirects' => [
                'max' => 5,
                'strict' => true,
                'referer' => true,
                'protocols' => ['http', 'https'],
                'track_redirects' => true,
            ],
        ])->get($url);
    }

    /**
     * Validate HTTP response and content.
     */
    private function validateResponse(Response $response, string $contents): void
    {
        if (! $response->successful()) {
            throw new \Exception('Failed to download file: HTTP '.$response->status());
        }

        if (empty($contents)) {
            throw new \Exception('Downloaded file is empty');
        }
    }

    /**
     * Handle HTML JavaScript redirects.
     */
    private function handleHtmlRedirect(string $contents, string $originalUrl): string
    {
        if (! $this->isHtmlContent($contents)) {
            return $contents;
        }

        $redirectUrl = $this->extractRedirectUrl($contents, $originalUrl);

        if ($redirectUrl) {
            try {
                return $this->downloadFromUrl($redirectUrl);
            } catch (\Exception $e) {
                throw new \Exception('Download failed: Got HTML redirect page instead of media file');
            }
        }

        throw new \Exception('Download failed: Got HTML content instead of media file');
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

    private function validateUrlSafety(string $url): void
    {
        $parsed = parse_url($url);

        if (! $parsed || ! isset($parsed['host'])) {
            throw new \InvalidArgumentException('Invalid URL: no host');
        }

        if (! isset($parsed['scheme']) || ! in_array($parsed['scheme'], ['http', 'https'], true)) {
            throw new \InvalidArgumentException('Invalid URL: only http and https schemes are allowed');
        }

        $host = $parsed['host'];

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ip = $host;
        } else {
            $ip = gethostbyname($host);
            if ($ip === $host) {
                throw new \InvalidArgumentException('Invalid URL: could not resolve host');
            }
        }

        $blockedRanges = [
            '0.0.0.0/8',
            '10.0.0.0/8',
            '100.64.0.0/10',
            '127.0.0.0/8',
            '169.254.0.0/16',
            '172.16.0.0/12',
            '192.0.0.0/24',
            '192.0.2.0/24',
            '192.168.0.0/16',
            '198.18.0.0/15',
            '198.51.100.0/24',
            '203.0.113.0/24',
            '224.0.0.0/4',
            '240.0.0.0/4',
        ];

        $ipLong = ip2long($ip);
        if ($ipLong === false) {
            throw new \InvalidArgumentException('Invalid URL: could not resolve host');
        }

        foreach ($blockedRanges as $range) {
            [$subnet, $bits] = explode('/', $range);
            $subnetLong = ip2long($subnet);
            $mask = ~((1 << (32 - (int) $bits)) - 1);

            if (($ipLong & $mask) === ($subnetLong & $mask)) {
                throw new \InvalidArgumentException('URL resolves to a private/internal IP address');
            }
        }
    }
}

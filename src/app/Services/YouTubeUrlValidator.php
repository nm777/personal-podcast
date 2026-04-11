<?php

namespace App\Services;

class YouTubeUrlValidator
{
    /**
     * Check if the given URL is a valid YouTube URL.
     */
    public static function isValidYouTubeUrl(string $url): bool
    {
        $patterns = [
            '#https?://(?:www\.|m\.)?youtube\.com/watch\?v=[\w-]+#',
            '#https?://(?:www\.|m\.)?youtube\.com/embed/[\w-]+#',
            '#https?://youtu\.be/[\w-]+#',
            '#https?://(?:www\.|m\.)?youtube\.com/shorts/[\w-]+#',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract the video ID from a YouTube URL.
     */
    public static function extractVideoId(string $url): ?string
    {
        $patterns = [
            '#https?://(?:www\.|m\.)?youtube\.com/watch\?v=([\w-]+)#',
            '#https?://(?:www\.|m\.)?youtube\.com/embed/([\w-]+)#',
            '#https?://youtu\.be/([\w-]+)#',
            '#https?://(?:www\.|m\.)?youtube\.com/shorts/([\w-]+)#',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                $id = $matches[1];

                if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $id)) {
                    return $id;
                }
            }
        }

        return null;
    }
}

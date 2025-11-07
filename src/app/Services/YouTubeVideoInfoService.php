<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;

class YouTubeVideoInfoService
{
    /**
     * Fetch video information from YouTube API.
     */
    public function getVideoInfo(string $videoId): ?array
    {
        try {
            // Use YouTube's oEmbed endpoint which doesn't require API key
            $response = Http::timeout(10)
                ->get('https://www.youtube.com/oembed', [
                    'url' => "https://www.youtube.com/watch?v={$videoId}",
                    'format' => 'json',
                ]);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();

            return [
                'title' => $data['title'] ?? null,
                'author_name' => $data['author_name'] ?? null,
                'thumbnail_url' => $data['thumbnail_url'] ?? null,
            ];
        } catch (Exception $e) {
            \Log::error('Failed to fetch YouTube video info: '.$e->getMessage());

            return null;
        }
    }
}

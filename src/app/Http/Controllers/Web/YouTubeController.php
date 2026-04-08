<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\YouTubeVideoInfoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class YouTubeController extends Controller
{
    public function __construct(
        private YouTubeVideoInfoService $youTubeVideoInfoService
    ) {}

    /**
     * Get YouTube video information.
     */
    public function getVideoInfo(string $videoId): JsonResponse
    {
        $cacheKey = "youtube.{$videoId}";
        $cacheDuration = config('constants.cache.youtube_info_duration_seconds');

        $videoInfo = Cache::remember($cacheKey, $cacheDuration, function() use ($videoId) {
            return $this->youTubeVideoInfoService->getVideoInfo($videoId);
        });

        if (! $videoInfo) {
            return response()->json(['error' => 'Video not found'], 404);
        }

        return response()->json($videoInfo);
    }
}

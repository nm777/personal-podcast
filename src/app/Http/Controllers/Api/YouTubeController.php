<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\YouTubeVideoInfoService;
use Illuminate\Http\JsonResponse;

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
        $videoInfo = $this->youTubeVideoInfoService->getVideoInfo($videoId);

        if (! $videoInfo) {
            return response()->json(['error' => 'Video not found'], 404);
        }

        return response()->json($videoInfo);
    }
}

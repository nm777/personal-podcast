<?php

namespace App\Http\Controllers;

use App\Models\Feed;
use App\Models\MediaFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function show(Request $request, string $file_path)
    {
        $mediaFile = MediaFile::where('file_path', $file_path)->firstOrFail();

        // Check if this is for an RSS feed (public or private with token)
        $feedToken = $request->query('feed_token');

        // For feeds without token (public feeds)
        if (! $feedToken) {
            $hasPublicFeed = Feed::where('is_public', true)
                ->whereHas('items', function ($query) use ($mediaFile) {
                    $query->whereHas('libraryItem', function ($query) use ($mediaFile) {
                        $query->where('media_file_id', $mediaFile->id);
                    });
                })
                ->exists();

            if ($hasPublicFeed) {
                return $this->serveMediaFile($file_path, $mediaFile);
            }
        }

        // For feeds with token (private feeds)
        if ($feedToken) {
            $hasFeedAccess = Feed::where('token', $feedToken)
                ->whereHas('items', function ($query) use ($mediaFile) {
                    $query->whereHas('libraryItem', function ($query) use ($mediaFile) {
                        $query->where('media_file_id', $mediaFile->id);
                    });
                })
                ->exists();

            if ($hasFeedAccess) {
                return $this->serveMediaFile($file_path, $mediaFile);
            }
        }

        // Ensure user can only access their own media files
        if (! Auth::check() || $mediaFile->user_id !== Auth::user()->id) {
            abort(403);
        }

        return $this->serveMediaFile($file_path, $mediaFile);
    }

    private function serveMediaFile(string $file_path, MediaFile $mediaFile): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        if (! Storage::disk('public')->exists($file_path)) {
            abort(404);
        }

        return Storage::disk('public')->download($file_path);
    }
}

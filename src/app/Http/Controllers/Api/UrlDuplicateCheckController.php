<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MediaFileResource;
use App\Models\LibraryItem;
use App\Models\MediaFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class UrlDuplicateCheckController extends Controller
{
    public function check(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'url' => 'required|url',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid URL'], 422);
        }

        $url = $request->input('url');

        $existingLibraryItem = LibraryItem::findBySourceUrlForUser($url, Auth::user()->id);
        $existingMediaFile = MediaFile::findBySourceUrl($url);

        // Check if user has either a library item or a media file with this URL
        $isDuplicate = $existingLibraryItem || ($existingMediaFile && $existingMediaFile->user_id === Auth::user()->id);
        $mediaFile = $existingLibraryItem?->mediaFile ?? ($existingMediaFile && $existingMediaFile->user_id === Auth::user()->id ? $existingMediaFile : null);

        return response()->json([
            'is_duplicate' => $isDuplicate ? true : false,
            'existing_file' => $mediaFile ? MediaFileResource::make($mediaFile) : null,
        ]);
    }
}

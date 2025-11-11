<?php

namespace App\Http\Controllers;

use App\Models\MediaFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function show(Request $request, string $file_path)
    {
        $mediaFile = MediaFile::where('file_path', $file_path)->firstOrFail();

        // Ensure user can only access their own media files
        if ($mediaFile->user_id !== auth()->id()) {
            abort(403);
        }

        if (! Storage::disk('public')->exists($file_path)) {
            abort(404);
        }

        // Serve the file directly instead of redirecting
        $file = Storage::disk('public')->get($file_path);
        $mimeType = $mediaFile->mime_type ?? 'application/octet-stream';

        return response($file)
            ->header('Content-Type', $mimeType)
            ->header('Content-Length', (string) strlen($file))
            ->header('Accept-Ranges', 'bytes');
    }
}

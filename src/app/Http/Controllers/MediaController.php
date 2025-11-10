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

        if (! Storage::disk('public')->exists($file_path)) {
            abort(404);
        }

        // Redirect to the public storage URL
        return redirect($mediaFile->public_url);
    }
}

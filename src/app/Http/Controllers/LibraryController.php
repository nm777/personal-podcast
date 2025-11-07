<?php

namespace App\Http\Controllers;

use App\Http\Requests\LibraryItemRequest;
use App\Jobs\CleanupDuplicateLibraryItem;
use App\Jobs\ProcessMediaFile;
use App\Jobs\ProcessYouTubeAudio;
use App\Models\LibraryItem;
use App\Models\MediaFile;
use App\Services\YouTubeUrlValidator;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class LibraryController extends Controller
{
    public function index()
    {
        $libraryItems = auth()->user()->libraryItems()
            ->with('mediaFile')
            ->latest()
            ->get();

        return Inertia::render('Library/Index', [
            'libraryItems' => $libraryItems,
        ]);
    }

    public function store(LibraryItemRequest $request)
    {
        $validated = $request->validated();

        // Handle backward compatibility for URL field
        $sourceType = $request->input('source_type', $request->hasFile('file') ? 'upload' : 'url');
        $sourceUrl = $request->input('source_url', $request->input('url'));

        // Additional validation for YouTube URLs
        if ($sourceType === 'youtube') {
            if (! YouTubeUrlValidator::isValidYouTubeUrl($sourceUrl)) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['source_url' => 'Invalid YouTube URL']);
            }
        }

        $mediaFileId = null;
        $message = '';

        // Check if URL already exists in our system
        if ($sourceUrl) {
            $existingMediaFile = MediaFile::findBySourceUrl($sourceUrl);

            if ($existingMediaFile) {
                $mediaFileId = $existingMediaFile->id;
                $message = 'Duplicate URL detected. This file already exists in your library and will be removed automatically in 5 minutes.';
            }
        }

        $libraryItem = LibraryItem::create([
            'user_id' => auth()->id(),
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'source_type' => $sourceType,
            'source_url' => $sourceUrl,
            'media_file_id' => $mediaFileId,
            'is_duplicate' => $mediaFileId ? true : false,
            'duplicate_detected_at' => $mediaFileId ? now() : null,
            'processing_status' => $mediaFileId ? 'completed' : 'pending',
            'processing_completed_at' => $mediaFileId ? now() : null,
        ]);

        if ($mediaFileId) {
            // File already exists, no processing needed
            $message = $message ?: 'Media file already exists. Added to your library.';

            // Schedule cleanup for duplicate URL entries
            if ($sourceUrl) {
                CleanupDuplicateLibraryItem::dispatch($libraryItem)->delay(now()->addMinutes(5));
            }
        } elseif ($sourceType === 'upload') {
            $file = $request->file('file');
            $tempPath = $file->store('temp-uploads');
            $fullTempPath = Storage::disk('local')->path($tempPath);

            // Check for duplicate by file hash
            $existingMediaFile = MediaFile::isDuplicate($fullTempPath);

            if ($existingMediaFile) {
                // Clean up temp file
                Storage::disk('local')->delete($tempPath);

                // Link to existing media file and mark as duplicate
                $libraryItem->media_file_id = $existingMediaFile->id;
                $libraryItem->is_duplicate = true;
                $libraryItem->duplicate_detected_at = now();
                $libraryItem->save();

                $message = 'Duplicate file detected. This file already exists in your library and will be removed automatically in 5 minutes.';

                // Schedule cleanup
                CleanupDuplicateLibraryItem::dispatch($libraryItem)->delay(now()->addMinutes(5));
            } else {
                // Process new file
                ProcessMediaFile::dispatch($libraryItem, null, $tempPath);
                $message = 'Media file uploaded successfully. Processing...';
            }
        } elseif ($sourceType === 'url') {
            ProcessMediaFile::dispatch($libraryItem, $sourceUrl, null);
            $message = 'Media file URL added successfully. Downloading and processing...';
        } elseif ($sourceType === 'youtube') {
            ProcessYouTubeAudio::dispatch($libraryItem, $sourceUrl);
            $message = 'YouTube video added successfully. Extracting audio...';
        }

        return redirect()->route('library.index')
            ->with('success', $message);
    }

    public function destroy($id)
    {
        $libraryItem = LibraryItem::findOrFail($id);

        // Ensure user can only delete their own library items
        if ($libraryItem->user_id !== auth()->id()) {
            abort(403);
        }

        $mediaFile = $libraryItem->mediaFile;
        $libraryItem->delete();

        // Check if this was the last reference to the media file
        if ($mediaFile && $mediaFile->libraryItems()->count() === 0) {
            Storage::disk('local')->delete($mediaFile->file_path);
            $mediaFile->delete();
        }

        return redirect()->route('library.index')
            ->with('success', 'Media file removed from your library.');
    }
}

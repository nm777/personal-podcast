<?php

namespace App\Http\Controllers;

use App\Http\Requests\LibraryItemRequest;
use App\Http\Requests\UpdateLibraryItemRequest;
use App\Models\LibraryItem;
use App\ProcessingStatusType;
use App\Services\SourceProcessors\SourceProcessorFactory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class LibraryController extends Controller
{
    public function index()
    {
        $libraryItems = Auth::user()->libraryItems()
            ->with('mediaFile')
            ->latest()
            ->get();

        $feeds = Auth::user()->feeds()->latest()->get();

        return Inertia::render('Library/Index', [
            'libraryItems' => $libraryItems,
            'feeds' => $feeds,
        ]);
    }

    public function store(LibraryItemRequest $request)
    {
        $validated = $request->validated();

        [$sourceType, $sourceUrl] = $this->getSourceTypeAndUrl($request);

        if ($redirectResponse = SourceProcessorFactory::validate($sourceType, $sourceUrl)) {
            return $redirectResponse;
        }

        // Use strategy pattern to process different source types
        $processor = SourceProcessorFactory::create($sourceType);
        [$libraryItem, $message] = $processor->process($request, $validated, $sourceType, $sourceUrl);

        // Add feed information to success message if feeds were selected
        if (! empty($validated['feed_ids'])) {
            $feedCount = count($validated['feed_ids']);
            $message .= " Item will be added to {$feedCount} feed".($feedCount > 1 ? 's' : '').' once processing completes.';
        }

        return redirect()->route('library.index')
            ->with('success', $message);
    }

    public function destroy($id)
    {
        $libraryItem = LibraryItem::findOrFail($id);

        Gate::authorize('delete', $libraryItem);

        $mediaFile = $libraryItem->mediaFile;

        // Clear RSS cache for feeds that contain this item
        $feedIds = $libraryItem->feedItems()->pluck('feed_id');
        foreach ($feedIds as $feedId) {
            Cache::forget("rss.{$feedId}");
        }

        $libraryItem->delete();

        // Check if this was the last reference to the media file
        if ($mediaFile && $mediaFile->libraryItems()->count() === 0) {
            Storage::disk('public')->delete($mediaFile->file_path);
            $mediaFile->delete();
        }

        return redirect()->route('library.index')
            ->with('success', 'Media file removed from your library.');
    }

    public function retry($id)
    {
        $libraryItem = LibraryItem::findOrFail($id);

        Gate::authorize('retry', $libraryItem);

        // Only allow retry for failed items
        if (! $libraryItem->hasFailed()) {
            return redirect()->route('library.index')
                ->with('warning', 'Only failed items can be retried.');
        }

        // Reset status to pending and clear error
        $libraryItem->update([
            'processing_status' => ProcessingStatusType::PENDING,
            'processing_error' => null,
            'processing_started_at' => now(),
            'processing_completed_at' => null,
        ]);

        // Re-dispatch the processing job based on source type
        $sourceType = $libraryItem->source_type;
        $processor = SourceProcessorFactory::create($sourceType);
        $processor->retry($libraryItem);

        return redirect()->route('library.index')
            ->with('success', 'Processing has been restarted.');
    }

    public function update(UpdateLibraryItemRequest $request, $id)
    {
        $libraryItem = LibraryItem::findOrFail($id);

        Gate::authorize('update', $libraryItem);

        $validated = $request->validated();

        $libraryItem->update($validated);

        return back()->with('success', 'Media file details updated successfully.');
    }

    /**
     * Get source type and URL from request, handling backward compatibility.
     */
    private function getSourceTypeAndUrl(LibraryItemRequest $request): array
    {
        $sourceType = $request->input('source_type', $request->hasFile('file') ? 'upload' : 'url');
        $sourceUrl = $request->input('source_url', $request->input('url'));

        return [$sourceType, $sourceUrl];
    }
}

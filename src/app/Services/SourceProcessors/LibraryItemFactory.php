<?php

namespace App\Services\SourceProcessors;

use App\Jobs\AddLibraryItemToFeedsJob;
use App\Models\LibraryItem;
use App\ProcessingStatusType;

class LibraryItemFactory
{
    /**
     * Create library item from validated data.
     */
    public function createFromValidated(array $validated, string $sourceType, ?string $sourceUrl = null, ?int $userId = null): LibraryItem
    {
        $libraryItem = LibraryItem::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'user_id' => $userId ?? auth()->id(),
            'source_type' => $sourceType,
            'source_url' => $sourceUrl,
            'processing_status' => ProcessingStatusType::PENDING,
        ]);

        $this->dispatchFeedJob($libraryItem, $validated);

        return $libraryItem;
    }

    /**
     * Create library item from validated data with media file data.
     */
    public function createFromValidatedWithMediaData(array $validated, string $sourceType, array $mediaFileData, ?int $userId = null): LibraryItem
    {
        $libraryItem = LibraryItem::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'user_id' => $userId ?? auth()->id(),
            'source_type' => $sourceType,
            'processing_status' => ProcessingStatusType::PENDING,
        ] + $mediaFileData);

        $this->dispatchFeedJob($libraryItem, $validated);

        return $libraryItem;
    }

    /**
     * Update library item with validated data while preserving existing media file relationship.
     */
    public function createFromValidatedWithMediaFile($mediaFile, array $validated, string $sourceType, ?string $sourceUrl = null, ?int $userId = null): LibraryItem
    {
        $currentUserId = $userId ?? auth()->id();
        $libraryItem = LibraryItem::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'user_id' => $currentUserId,
            'source_type' => $sourceType,
            'source_url' => $sourceUrl,
            'media_file_id' => $mediaFile->id,
            'is_duplicate' => $mediaFile->user_id === $currentUserId,
            'duplicate_detected_at' => $mediaFile->user_id === $currentUserId ? now() : null,
            'processing_status' => ProcessingStatusType::COMPLETED,
            'processing_completed_at' => now(),
        ]);

        $this->dispatchFeedJob($libraryItem, $validated);

        return $libraryItem;
    }

    /**
     * Dispatch job to add library item to feeds after processing completes.
     */
    private function dispatchFeedJob(LibraryItem $libraryItem, array $validated): void
    {
        if (empty($validated['feed_ids'])) {
            return;
        }

        // Only dispatch job for items that need processing
        if ($libraryItem->isPending() || $libraryItem->isProcessing()) {
            AddLibraryItemToFeedsJob::dispatch($libraryItem, $validated['feed_ids'])
                ->delay(now()->addSeconds(30)); // Give some time for processing to start
        } else {
            // For completed items, add to feeds immediately
            AddLibraryItemToFeedsJob::dispatchSync($libraryItem, $validated['feed_ids']);
        }
    }
}

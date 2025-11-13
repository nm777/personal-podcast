<?php

namespace App\Services\SourceProcessors;

use App\Models\LibraryItem;
use App\ProcessingStatusType;

class LibraryItemFactory
{
    /**
     * Create library item from validated data.
     */
    public function createFromValidated(array $validated, string $sourceType, ?string $sourceUrl = null, ?int $userId = null): LibraryItem
    {
        return LibraryItem::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'user_id' => $userId ?? auth()->id(),
            'source_type' => $sourceType,
            'source_url' => $sourceUrl,
            'processing_status' => ProcessingStatusType::PENDING,
        ]);
    }

    /**
     * Create library item from validated data with media file data.
     */
    public function createFromValidatedWithMediaData(array $validated, string $sourceType, array $mediaFileData, ?int $userId = null): LibraryItem
    {
        return LibraryItem::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'user_id' => $userId ?? auth()->id(),
            'source_type' => $sourceType,
            'processing_status' => ProcessingStatusType::PENDING,
        ] + $mediaFileData);
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

        return $libraryItem;
    }
}

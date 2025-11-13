<?php

namespace App\Services\SourceProcessors;

use App\Models\LibraryItem;
use App\Services\DuplicateDetectionService;

class UrlSourceProcessor
{
    public function __construct(
        private LibraryItemFactory $libraryItemFactory,
        private SourceStrategyInterface $strategy
    ) {}

    /**
     * Handle URL source processing.
     */
    public function process(array $validated, string $sourceType, ?string $sourceUrl): array
    {
        $userId = auth()->id();

        // Check for user duplicates first (before creating any library item)
        $userDuplicate = DuplicateDetectionService::findUrlDuplicateForUser($sourceUrl, $userId);

        if ($userDuplicate) {
            // User already has this URL - update existing library item with new data, mark as duplicate, and return duplicate message
            $userDuplicate->update([
                'title' => $validated['title'] ?? $userDuplicate->title,
                'description' => $validated['description'] ?? $userDuplicate->description,
                'is_duplicate' => true,
            ]);

            return [$userDuplicate, $this->strategy->getSuccessMessage(true)];
        }

        // Check for user media file only (edge case where user has MediaFile but no LibraryItem)
        $globalDuplicate = DuplicateDetectionService::findGlobalUrlDuplicate($sourceUrl);
        if ($globalDuplicate && $globalDuplicate->user_id === $userId && ! DuplicateDetectionService::findUrlDuplicateForUser($sourceUrl, $userId)) {
            // Create library item linking to existing user media file
            $libraryItem = $this->libraryItemFactory->createFromValidatedWithMediaFile(
                $globalDuplicate,
                $validated,
                $sourceType,
                $sourceUrl,
                $userId
            );

            return [$libraryItem, $this->strategy->getSuccessMessage(true)];
        }

        // Check for global duplicates from other users
        if ($globalDuplicate && $globalDuplicate->user_id !== $userId) {
            // Create library item linking to global duplicate and mark as cross-user duplicate
            $libraryItem = $this->libraryItemFactory->createFromValidatedWithMediaFile(
                $globalDuplicate,
                $validated,
                $sourceType,
                $sourceUrl,
                $userId
            );
            $libraryItem->update(['is_duplicate' => true]);

            return [$libraryItem, $this->strategy->getSuccessMessage(true)];
        }

        // No duplicates found - create new library item and process
        $libraryItem = $this->libraryItemFactory->createFromValidated($validated, $sourceType, $sourceUrl, $userId);
        $this->strategy->processNewSource($libraryItem, $sourceUrl);

        return [$libraryItem, $this->strategy->getProcessingMessage()];
    }
}

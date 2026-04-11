<?php

namespace App\Services\SourceProcessors;

use App\Services\MediaProcessing\UnifiedDuplicateProcessor;

class UrlSourceProcessor
{
    public function __construct(
        private LibraryItemFactory $libraryItemFactory,
        private SourceStrategyInterface $strategy,
        private UnifiedDuplicateProcessor $duplicateProcessor
    ) {}

    /**
     * Handle URL source processing.
     */
    public function process(array $validated, string $sourceType, ?string $sourceUrl): array
    {
        $userId = auth()->id();

        $analysis = $this->duplicateProcessor->analyzeUrlDuplicate($sourceUrl, $userId);

        if ($analysis['should_link_to_user_duplicate']) {
            $existingItem = $analysis['user_duplicate_library_item'];
            $existingItem->update([
                'title' => $validated['title'] ?? $existingItem->title,
                'description' => $validated['description'] ?? $existingItem->description,
                'is_duplicate' => true,
            ]);

            return [$existingItem, $this->strategy->getSuccessMessage(true)];
        }

        if ($analysis['should_link_to_user_media_file'] || $analysis['should_link_to_global_duplicate']) {
            $mediaFile = $analysis['global_duplicate_media_file'];
            $libraryItem = $this->libraryItemFactory->createFromValidatedWithMediaFile(
                $mediaFile,
                $validated,
                $sourceType,
                $sourceUrl,
                $userId
            );

            if ($analysis['should_link_to_global_duplicate']) {
                $libraryItem->update(['is_duplicate' => true]);
            }

            return [$libraryItem, $this->strategy->getSuccessMessage(true)];
        }

        $libraryItem = $this->libraryItemFactory->createFromValidated($validated, $sourceType, $sourceUrl, $userId);
        $this->strategy->processNewSource($libraryItem, $sourceUrl);

        return [$libraryItem, $this->strategy->getProcessingMessage()];
    }
}

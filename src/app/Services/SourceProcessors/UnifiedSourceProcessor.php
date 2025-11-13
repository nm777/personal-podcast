<?php

namespace App\Services\SourceProcessors;

use App\Http\Requests\LibraryItemRequest;

class UnifiedSourceProcessor
{
    public function __construct(
        private FileUploadProcessor $fileUploadProcessor,
        private UrlSourceProcessor $urlSourceProcessor,
        private SourceStrategyInterface $strategy
    ) {}

    /**
     * Process source using unified logic with strategy pattern.
     */
    public function process(LibraryItemRequest $request, array $validated, string $sourceType, ?string $sourceUrl): array
    {
        // Validate source using strategy
        $this->strategy->validate($sourceUrl);

        // Handle file upload for new items
        if ($sourceType === 'upload') {
            return $this->fileUploadProcessor->process($request, $validated, $sourceType);
        }

        // Handle URL sources (YouTube, regular URL)
        return $this->urlSourceProcessor->process($validated, $sourceType, $sourceUrl);
    }
}

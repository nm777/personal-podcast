<?php

namespace App\Services\SourceProcessors;

use App\Http\Requests\LibraryItemRequest;
use App\Jobs\ProcessMediaFile;
use App\Services\MediaProcessing\UnifiedDuplicateProcessor;
use Illuminate\Support\Facades\Storage;

class FileUploadProcessor
{
    public function __construct(
        private UnifiedDuplicateProcessor $duplicateProcessor,
        private LibraryItemFactory $libraryItemFactory
    ) {}

    /**
     * Handle file upload processing.
     */
    public function process(LibraryItemRequest $request, array $validated, string $sourceType): array
    {
        $file = $request->file('file');
        $tempPath = $file->store('temp-uploads', 'public');
        $userId = auth()->id();

        // Create temporary library item for duplicate checking
        $tempLibraryItem = $this->libraryItemFactory->createFromValidated($validated, $sourceType, null, $userId);

        // Check for file duplicates
        $duplicateResult = $this->duplicateProcessor->processFileDuplicate($tempLibraryItem, $tempPath);

        if ($duplicateResult['media_file']) {
            // Clean up temp file
            Storage::disk('public')->delete($tempPath);

            // Delete temporary library item
            $tempLibraryItem->delete();

            // Create final library item with user-provided data and existing media file
            $libraryItem = $this->libraryItemFactory->createFromValidatedWithMediaFile(
                $duplicateResult['media_file'],
                $validated,
                $sourceType,
                null,
                $userId
            );

            return [$libraryItem, $this->getSuccessMessage($duplicateResult['is_duplicate'])];
        }

        // Delete temporary library item and create new one for processing
        $tempLibraryItem->delete();
        $libraryItem = $this->libraryItemFactory->createFromValidatedWithMediaData($validated, $sourceType, [
            'file_path' => $tempPath,
            'file_hash' => hash('sha256', Storage::disk('public')->get($tempPath)),
            'mime_type' => $file->getMimeType(),
            'filesize' => $file->getSize(),
        ], $userId);

        // Process new file
        ProcessMediaFile::dispatch($libraryItem, null, $tempPath);

        return [$libraryItem, $this->getProcessingMessage()];
    }

    private function getSuccessMessage(bool $isDuplicate): string
    {
        return $isDuplicate
            ? 'Duplicate file detected. This file already exists in your library and will be removed automatically in 5 minutes.'
            : 'Media file uploaded successfully. Processing...';
    }

    private function getProcessingMessage(): string
    {
        return 'Media file uploaded successfully. Processing...';
    }
}

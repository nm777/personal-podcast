<?php

namespace App\Services\MediaProcessing;

use App\Jobs\CleanupDuplicateLibraryItem;
use App\Models\LibraryItem;
use App\Models\MediaFile;
use App\ProcessingStatusType;
use App\Services\DuplicateDetectionService;
use Illuminate\Support\Facades\Log;

class UnifiedDuplicateProcessor
{
    /**
     * Process duplicate detection and handling for URL sources.
     */
    public function processUrlDuplicate(LibraryItem $libraryItem, string $sourceUrl): array
    {
        $duplicateAnalysis = DuplicateDetectionService::analyzeUrlSource(
            $sourceUrl,
            $libraryItem->user_id,
            $libraryItem->id
        );

        if ($duplicateAnalysis['should_link_to_user_duplicate']) {
            return $this->handleUserUrlDuplicate($libraryItem, $duplicateAnalysis);
        }

        if ($duplicateAnalysis['should_link_to_user_media_file']) {
            return $this->handleUserMediaFileOnly($libraryItem, $duplicateAnalysis);
        }

        if ($duplicateAnalysis['should_link_to_global_duplicate']) {
            return $this->handleGlobalUrlDuplicate($libraryItem, $duplicateAnalysis, $sourceUrl);
        }

        return $this->buildSuccessResponse(false, null);
    }

    /**
     * Process duplicate detection and handling for file uploads.
     */
    public function processFileDuplicate(LibraryItem $libraryItem, string $filePath): array
    {
        $duplicateAnalysis = DuplicateDetectionService::analyzeFileUpload($filePath, $libraryItem->user_id);

        if ($duplicateAnalysis['should_link_to_user_duplicate']) {
            return $this->handleUserFileDuplicate($libraryItem, $duplicateAnalysis, $filePath);
        }

        if ($duplicateAnalysis['should_link_to_global_duplicate']) {
            return $this->handleGlobalFileDuplicate($libraryItem, $duplicateAnalysis, $filePath);
        }

        return $this->buildSuccessResponse(false, null);
    }

    /**
     * Build standard success response array.
     */
    private function buildSuccessResponse(bool $isDuplicate, ?MediaFile $mediaFile, string $message = null): array
    {
        $response = [
            'success' => true,
            'is_duplicate' => $isDuplicate,
            'media_file' => $mediaFile,
        ];

        if ($message) {
            $response['message'] = $message;
        }

        return $response;
    }

    /**
     * Mark library item as completed and optionally as duplicate.
     */
    private function markAsCompleted(LibraryItem $libraryItem, bool $isDuplicate = false): void
    {
        $updateData = [
            'processing_status' => ProcessingStatusType::COMPLETED,
            'processing_completed_at' => now(),
        ];

        if ($isDuplicate) {
            $libraryItem->is_duplicate = true;
            $libraryItem->duplicate_detected_at = now();
        }

        $libraryItem->update($updateData);
    }

    /**
     * Schedule cleanup job for duplicate library item.
     */
    private function scheduleCleanup(LibraryItem $libraryItem): void
    {
        CleanupDuplicateLibraryItem::dispatch($libraryItem)->delay(
            now()->addMinutes(config('constants.duplicate.cleanup_delay_minutes'))
        );
    }

    /**
     * Handle user duplicate for URL sources.
     */
    private function handleUserUrlDuplicate(LibraryItem $libraryItem, array $duplicateAnalysis): array
    {
        $existingLibraryItem = $duplicateAnalysis['user_duplicate_library_item'];

        Log::info('Found existing library item for URL', [
            'library_item_id' => $libraryItem->id,
            'existing_library_item_id' => $existingLibraryItem->id,
            'existing_media_file_id' => $existingLibraryItem->media_file_id,
        ]);

        $libraryItem->media_file_id = $existingLibraryItem->media_file_id;
        $this->markAsCompleted($libraryItem, true);
        $this->scheduleCleanup($libraryItem);

        return $this->buildSuccessResponse(
            true,
            $existingLibraryItem->mediaFile,
            'Duplicate file detected. This file already exists in your library and will be removed automatically in ' . config('constants.duplicate.cleanup_delay_minutes') . ' minutes.'
        );
    }

    /**
     * Handle case where user has MediaFile but no LibraryItem for URL.
     */
    private function handleUserMediaFileOnly(LibraryItem $libraryItem, array $duplicateAnalysis): array
    {
        $mediaFile = $duplicateAnalysis['global_duplicate_media_file'];

        Log::info('Found existing media file for user (no library item)', [
            'library_item_id' => $libraryItem->id,
            'media_file_id' => $mediaFile->id,
        ]);

        $libraryItem->media_file_id = $mediaFile->id;
        $this->markAsCompleted($libraryItem);

        return $this->buildSuccessResponse(
            false,
            $mediaFile,
            'File already exists in your library. Linked to existing media file.'
        );
    }

    /**
     * Handle global duplicate for URL sources.
     */
    private function handleGlobalUrlDuplicate(LibraryItem $libraryItem, array $duplicateAnalysis, string $sourceUrl): array
    {
        $globalMediaFile = $duplicateAnalysis['global_duplicate_media_file'];

        Log::info('Found existing media file from different user for URL', [
            'library_item_id' => $libraryItem->id,
            'existing_media_file_id' => $globalMediaFile->id,
            'existing_user_id' => $globalMediaFile->user_id,
            'current_user_id' => $libraryItem->user_id,
            'source_url' => $sourceUrl,
        ]);

        $libraryItem->media_file_id = $globalMediaFile->id;
        $this->markAsCompleted($libraryItem);

        return $this->buildSuccessResponse(
            false,
            $globalMediaFile,
            'File already exists in system. Linked to existing media file.'
        );
    }

    /**
     * Handle user duplicate for file uploads.
     */
    private function handleUserFileDuplicate(LibraryItem $libraryItem, array $duplicateAnalysis, string $filePath): array
    {
        $userDuplicateMediaFile = $duplicateAnalysis['user_duplicate_media_file'];

        // Update source URL if this is first time we've seen it from a URL
        if ($libraryItem->source_url && ! $userDuplicateMediaFile->source_url) {
            $userDuplicateMediaFile->source_url = $libraryItem->source_url;
            $userDuplicateMediaFile->save();
        }

        $libraryItem->media_file_id = $userDuplicateMediaFile->id;
        $this->markAsCompleted($libraryItem, true);
        $this->scheduleCleanup($libraryItem);

        return $this->buildSuccessResponse(
            true,
            $userDuplicateMediaFile,
            'Duplicate file detected. This file already exists in your library and will be removed automatically in ' . config('constants.duplicate.cleanup_delay_minutes') . ' minutes.'
        );
    }

    /**
     * Handle global duplicate for file uploads.
     */
    private function handleGlobalFileDuplicate(LibraryItem $libraryItem, array $duplicateAnalysis, string $filePath): array
    {
        $globalDuplicateMediaFile = $duplicateAnalysis['global_duplicate_media_file'];

        $libraryItem->media_file_id = $globalDuplicateMediaFile->id;
        $this->markAsCompleted($libraryItem);

        return $this->buildSuccessResponse(
            false,
            $globalDuplicateMediaFile,
            'File already exists in system. Linked to existing media file.'
        );
    }
}

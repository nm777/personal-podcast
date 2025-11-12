<?php

namespace App\Services\MediaProcessing;

use App\Jobs\CleanupDuplicateLibraryItem;
use App\Models\LibraryItem;
use App\ProcessingStatusType;
use App\Services\DuplicateDetectionService;
use Illuminate\Support\Facades\Log;

class DuplicateHandler
{
    public function __construct(
        private MediaStorageManager $storageManager
    ) {}

    /**
     * Handle duplicate detection for file uploads.
     */
    public function handleFileDuplicate(LibraryItem $libraryItem, string $tempPath): array
    {
        $duplicateAnalysis = DuplicateDetectionService::analyzeFileUpload($tempPath, $libraryItem->user_id);

        if ($duplicateAnalysis['should_link_to_user_duplicate']) {
            return $this->handleUserDuplicate($libraryItem, $duplicateAnalysis, $tempPath);
        }

        if ($duplicateAnalysis['should_link_to_global_duplicate']) {
            return $this->handleGlobalDuplicate($libraryItem, $duplicateAnalysis, $tempPath);
        }

        return ['is_duplicate' => false, 'media_file' => null];
    }

    /**
     * Handle duplicate detection for URL sources.
     */
    public function handleUrlDuplicate(LibraryItem $libraryItem, string $sourceUrl): array
    {
        $duplicateAnalysis = DuplicateDetectionService::analyzeUrlSource(
            $sourceUrl,
            $libraryItem->user_id,
            $libraryItem->id
        );

        if ($duplicateAnalysis['should_link_to_user_duplicate']) {
            return $this->handleUrlUserDuplicate($libraryItem, $duplicateAnalysis);
        }

        if ($duplicateAnalysis['should_link_to_global_duplicate']) {
            return $this->handleUrlGlobalDuplicate($libraryItem, $duplicateAnalysis, $sourceUrl);
        }

        return ['is_duplicate' => false, 'media_file' => null];
    }

    /**
     * Handle user duplicate for file uploads.
     */
    private function handleUserDuplicate(LibraryItem $libraryItem, array $duplicateAnalysis, string $tempPath): array
    {
        $this->storageManager->cleanupTempFile($tempPath);

        $userDuplicateMediaFile = $duplicateAnalysis['user_duplicate_media_file'];

        // Update source URL if this is first time we've seen it from a URL
        if ($libraryItem->source_url && ! $userDuplicateMediaFile->source_url) {
            $userDuplicateMediaFile->source_url = $libraryItem->source_url;
            $userDuplicateMediaFile->save();
        }

        // Mark this library item as a duplicate
        $libraryItem->media_file_id = $userDuplicateMediaFile->id;
        $libraryItem->is_duplicate = true;
        $libraryItem->duplicate_detected_at = now();
        $libraryItem->update([
            'processing_status' => ProcessingStatusType::COMPLETED,
            'processing_completed_at' => now(),
        ]);

        // Schedule cleanup of this duplicate entry
        CleanupDuplicateLibraryItem::dispatch($libraryItem)->delay(now()->addMinutes(5));

        // Store flash message for user notification
        session()->flash('warning', 'Duplicate file detected. This file already exists in your library and will be removed automatically in 5 minutes.');

        return [
            'is_duplicate' => true,
            'media_file' => $userDuplicateMediaFile,
            'message' => 'Duplicate file detected. This file already exists in your library and will be removed automatically in 5 minutes.',
        ];
    }

    /**
     * Handle global duplicate for file uploads.
     */
    private function handleGlobalDuplicate(LibraryItem $libraryItem, array $duplicateAnalysis, string $tempPath): array
    {
        $this->storageManager->cleanupTempFile($tempPath);

        $globalDuplicateMediaFile = $duplicateAnalysis['global_duplicate_media_file'];
        $libraryItem->media_file_id = $globalDuplicateMediaFile->id;

        // Don't mark as duplicate since this is a different user's file
        $libraryItem->update([
            'processing_status' => ProcessingStatusType::COMPLETED,
            'processing_completed_at' => now(),
        ]);

        // Store flash message for user notification
        session()->flash('info', 'File already exists in the system. Linked to existing media file.');

        return [
            'is_duplicate' => false,
            'media_file' => $globalDuplicateMediaFile,
            'message' => 'File already exists in the system. Linked to existing media file.',
        ];
    }

    /**
     * Handle user duplicate for URL sources.
     */
    private function handleUrlUserDuplicate(LibraryItem $libraryItem, array $duplicateAnalysis): array
    {
        // User already has this URL, link to existing media file and mark as completed
        $libraryItem->media_file_id = $duplicateAnalysis['user_duplicate_library_item']->media_file_id;
        $libraryItem->update([
            'processing_status' => ProcessingStatusType::COMPLETED,
            'processing_completed_at' => now(),
        ]);

        return [
            'is_duplicate' => true,
            'media_file' => $duplicateAnalysis['user_duplicate_library_item']->mediaFile,
            'message' => 'This URL has already been processed. The existing media file has been linked to this library item.',
        ];
    }

    /**
     * Handle global duplicate for URL sources.
     */
    private function handleUrlGlobalDuplicate(LibraryItem $libraryItem, array $duplicateAnalysis, string $sourceUrl): array
    {
        Log::info('Found existing media file from different user for URL', [
            'library_item_id' => $libraryItem->id,
            'existing_media_file_id' => $duplicateAnalysis['global_duplicate_media_file']->id,
            'existing_user_id' => $duplicateAnalysis['global_duplicate_media_file']->user_id,
            'current_user_id' => $libraryItem->user_id,
            'source_url' => $sourceUrl,
        ]);

        // Link to existing media file from different user (cross-user sharing)
        $libraryItem->media_file_id = $duplicateAnalysis['global_duplicate_media_file']->id;
        $libraryItem->update([
            'processing_status' => ProcessingStatusType::COMPLETED,
            'processing_completed_at' => now(),
        ]);

        return [
            'is_duplicate' => false,
            'media_file' => $duplicateAnalysis['global_duplicate_media_file'],
            'message' => 'File already exists in the system. Linked to existing media file.',
        ];
    }
}

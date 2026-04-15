<?php

use App\Jobs\ProcessYouTubeAudio;
use App\Models\LibraryItem;
use App\Models\User;
use App\ProcessingStatusType;
use App\Services\MediaProcessing\UnifiedDuplicateProcessor;
use App\Services\YouTube\YouTubeDownloader;
use App\Services\YouTube\YouTubeFileProcessor;
use App\Services\YouTube\YouTubeMetadataExtractor;
use App\Services\YouTube\YouTubeProcessingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

it('processes YouTube audio job with logging', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'source_type' => 'youtube',
        'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ]);

    // Capture logs - expect info logs since job should start and process
    Log::shouldReceive('info')->atLeast()->once();
    Log::shouldReceive('error')->atLeast()->once();

    $job = new ProcessYouTubeAudio($libraryItem, 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');

    // Mock the processing service to avoid actual YouTube processing
    $processingService = mock(YouTubeProcessingService::class);
    $processingService->shouldReceive('processYouTubeUrl')
        ->once()
        ->andThrow(new Exception('Test error'));

    // Mock the yt-dlp command to fail so we can test error logging
    $job->handle($processingService);
});

it('marks library item as failed when video ID extraction fails instead of deleting', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'source_type' => 'youtube',
        'source_url' => 'https://www.youtube.com/invalid',
    ]);

    $service = app(YouTubeProcessingService::class);

    $result = $service->processYouTubeUrl($libraryItem, 'https://www.youtube.com/invalid');

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toBe('Invalid YouTube URL');

    $libraryItem->refresh();
    expect($libraryItem)->not->toBeNull();
    expect($libraryItem->processing_status)->toBe(ProcessingStatusType::FAILED);
    expect($libraryItem->processing_error)->not->toBeNull();
    expect($libraryItem->processing_completed_at)->not->toBeNull();

    $this->assertDatabaseHas('library_items', ['id' => $libraryItem->id]);
});

it('marks library item as failed when download fails instead of deleting', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'source_type' => 'youtube',
        'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ]);

    $downloader = mock(YouTubeDownloader::class);
    $downloader->shouldReceive('downloadAudio')->andReturn(null);
    $downloader->shouldReceive('cleanupTempDirectory');

    $metadataExtractor = mock(YouTubeMetadataExtractor::class);
    $fileProcessor = mock(YouTubeFileProcessor::class);
    $duplicateProcessor = mock(UnifiedDuplicateProcessor::class);
    $duplicateProcessor->shouldReceive('processUrlDuplicate')->andReturn(['is_duplicate' => false]);

    $service = new YouTubeProcessingService($downloader, $metadataExtractor, $fileProcessor, $duplicateProcessor);
    $result = $service->processYouTubeUrl($libraryItem, 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toBe('Failed to download YouTube video');

    $libraryItem->refresh();
    expect($libraryItem->processing_status)->toBe(ProcessingStatusType::FAILED);
    expect($libraryItem->processing_error)->not->toBeNull();
    $this->assertDatabaseHas('library_items', ['id' => $libraryItem->id]);
});

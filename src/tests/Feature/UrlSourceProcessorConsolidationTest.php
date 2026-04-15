<?php

use App\Http\Requests\LibraryItemRequest;
use App\Models\LibraryItem;
use App\Models\MediaFile;
use App\Models\User;
use App\Services\MediaProcessing\UnifiedDuplicateProcessor;
use App\Services\SourceProcessors\LibraryItemFactory;
use App\Services\SourceProcessors\SourceProcessorFactory;
use App\Services\SourceProcessors\UrlSourceProcessor;
use App\Services\SourceProcessors\UrlStrategy;

describe('UrlSourceProcessor consolidation', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    it('delegates URL duplicate analysis to UnifiedDuplicateProcessor for user duplicates', function () {
        $existingMediaFile = MediaFile::factory()->create([
            'user_id' => $this->user->id,
            'source_url' => 'https://example.com/audio.mp3',
        ]);

        $existingLibraryItem = LibraryItem::factory()->create([
            'user_id' => $this->user->id,
            'media_file_id' => $existingMediaFile->id,
            'source_url' => 'https://example.com/audio.mp3',
        ]);

        $duplicateProcessor = Mockery::mock(UnifiedDuplicateProcessor::class);
        $duplicateProcessor->shouldReceive('analyzeUrlDuplicate')
            ->once()
            ->with('https://example.com/audio.mp3', $this->user->id)
            ->andReturn([
                'is_user_duplicate' => true,
                'is_global_duplicate' => true,
                'user_duplicate_library_item' => $existingLibraryItem,
                'global_duplicate_media_file' => $existingMediaFile,
                'user_media_file_only' => false,
                'should_link_to_user_duplicate' => true,
                'should_link_to_user_media_file' => false,
                'should_link_to_global_duplicate' => false,
                'should_create_new_file' => false,
            ]);

        $processor = new UrlSourceProcessor(
            new LibraryItemFactory,
            new UrlStrategy,
            $duplicateProcessor
        );

        $result = $processor->process(
            ['title' => 'Updated Title', 'description' => 'New Desc'],
            'url',
            'https://example.com/audio.mp3'
        );

        expect($result)->toHaveCount(2);
        [$libraryItem, $message] = $result;
        expect($libraryItem->id)->toBe($existingLibraryItem->id);
        expect($libraryItem->title)->toBe('Updated Title');
        expect($libraryItem->is_duplicate)->toBeTrue();
        expect($message)->toContain('already been processed');
    });

    it('delegates URL duplicate analysis to UnifiedDuplicateProcessor for global duplicates', function () {
        $otherUser = User::factory()->create();
        $globalMediaFile = MediaFile::factory()->create([
            'user_id' => $otherUser->id,
            'source_url' => 'https://example.com/global-audio.mp3',
        ]);

        $duplicateProcessor = Mockery::mock(UnifiedDuplicateProcessor::class);
        $duplicateProcessor->shouldReceive('analyzeUrlDuplicate')
            ->once()
            ->andReturn([
                'is_user_duplicate' => false,
                'is_global_duplicate' => true,
                'user_duplicate_library_item' => null,
                'global_duplicate_media_file' => $globalMediaFile,
                'user_media_file_only' => false,
                'should_link_to_user_duplicate' => false,
                'should_link_to_user_media_file' => false,
                'should_link_to_global_duplicate' => true,
                'should_create_new_file' => false,
            ]);

        $processor = new UrlSourceProcessor(
            new LibraryItemFactory,
            new UrlStrategy,
            $duplicateProcessor
        );

        $result = $processor->process(
            ['title' => 'Test', 'description' => 'Desc'],
            'url',
            'https://example.com/global-audio.mp3'
        );

        expect($result)->toHaveCount(2);
        [$libraryItem, $message] = $result;
        expect($libraryItem)->toBeInstanceOf(LibraryItem::class);
        expect($libraryItem->media_file_id)->toBe($globalMediaFile->id);
        expect($libraryItem->is_duplicate)->toBeTrue();
        expect($message)->toContain('already been processed');
    });

    it('delegates URL duplicate analysis to UnifiedDuplicateProcessor when no duplicate found', function () {
        $duplicateProcessor = Mockery::mock(UnifiedDuplicateProcessor::class);
        $duplicateProcessor->shouldReceive('analyzeUrlDuplicate')
            ->once()
            ->andReturn([
                'is_user_duplicate' => false,
                'is_global_duplicate' => false,
                'user_duplicate_library_item' => null,
                'global_duplicate_media_file' => null,
                'user_media_file_only' => false,
                'should_link_to_user_duplicate' => false,
                'should_link_to_user_media_file' => false,
                'should_link_to_global_duplicate' => false,
                'should_create_new_file' => true,
            ]);

        $processor = new UrlSourceProcessor(
            new LibraryItemFactory,
            new UrlStrategy,
            $duplicateProcessor
        );

        $result = $processor->process(
            ['title' => 'New File', 'description' => 'Desc'],
            'url',
            'https://example.com/new-audio.mp3'
        );

        expect($result)->toHaveCount(2);
        [$libraryItem, $message] = $result;
        expect($libraryItem)->toBeInstanceOf(LibraryItem::class);
        expect($libraryItem->source_url)->toBe('https://example.com/new-audio.mp3');
        expect($libraryItem->media_file_id)->toBeNull();
        expect($message)->toBe('URL added successfully. Processing...');
    });

    it('integrates end-to-end via SourceProcessorFactory with real UnifiedDuplicateProcessor', function () {
        $existingMediaFile = MediaFile::factory()->create([
            'user_id' => $this->user->id,
            'source_url' => 'https://example.com/dup.mp3',
        ]);

        LibraryItem::factory()->create([
            'user_id' => $this->user->id,
            'media_file_id' => $existingMediaFile->id,
            'source_url' => 'https://example.com/dup.mp3',
        ]);

        $processor = SourceProcessorFactory::create('url');

        $result = $processor->process(
            new LibraryItemRequest,
            ['title' => 'Duplicate Test', 'description' => 'Desc'],
            'url',
            'https://example.com/dup.mp3'
        );

        expect($result)->toHaveCount(2);
        [$libraryItem, $message] = $result;
        expect($libraryItem->media_file_id)->toBe($existingMediaFile->id);
        expect($libraryItem->is_duplicate)->toBeTrue();
    });
});

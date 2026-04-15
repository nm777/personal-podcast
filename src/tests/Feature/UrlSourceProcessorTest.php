<?php

use App\Models\MediaFile;
use App\Models\User;
use App\Services\MediaProcessing\UnifiedDuplicateProcessor;
use App\Services\SourceProcessors\LibraryItemFactory;
use App\Services\SourceProcessors\SourceStrategyInterface;
use App\Services\SourceProcessors\UrlSourceProcessor;

describe('UrlSourceProcessor', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    it('can be instantiated with dependencies', function () {
        $libraryItemFactory = new LibraryItemFactory;
        $strategy = Mockery::mock(SourceStrategyInterface::class);
        $duplicateProcessor = Mockery::mock(UnifiedDuplicateProcessor::class);

        $processor = new UrlSourceProcessor($libraryItemFactory, $strategy, $duplicateProcessor);

        expect($processor)->toBeInstanceOf(UrlSourceProcessor::class);
    });

    it('delegates duplicate detection to UnifiedDuplicateProcessor', function () {
        $duplicateProcessor = Mockery::mock(UnifiedDuplicateProcessor::class);
        $duplicateProcessor->shouldReceive('analyzeUrlDuplicate')
            ->once()
            ->andReturn([
                'should_link_to_user_duplicate' => false,
                'should_link_to_user_media_file' => false,
                'should_link_to_global_duplicate' => false,
                'should_create_new_file' => true,
                'user_duplicate_library_item' => null,
                'global_duplicate_media_file' => null,
                'is_user_duplicate' => false,
                'is_global_duplicate' => false,
                'user_media_file_only' => false,
            ]);

        $strategy = Mockery::mock(SourceStrategyInterface::class);
        $strategy->shouldReceive('processNewSource')->once();
        $strategy->shouldReceive('getProcessingMessage')->once()->andReturn('Processing...');

        $processor = new UrlSourceProcessor(
            new LibraryItemFactory,
            $strategy,
            $duplicateProcessor
        );

        $result = $processor->process(
            ['title' => 'Test', 'description' => 'Desc'],
            'url',
            'https://example.com/test.mp3'
        );

        expect($result)->toHaveCount(2);
    });

    it('handles user media file only edge case via delegate', function () {
        $mediaFile = MediaFile::factory()->create([
            'user_id' => $this->user->id,
            'source_url' => 'https://example.com/orphan.mp3',
        ]);

        $duplicateProcessor = Mockery::mock(UnifiedDuplicateProcessor::class);
        $duplicateProcessor->shouldReceive('analyzeUrlDuplicate')
            ->once()
            ->andReturn([
                'should_link_to_user_duplicate' => false,
                'should_link_to_user_media_file' => true,
                'should_link_to_global_duplicate' => false,
                'should_create_new_file' => false,
                'user_duplicate_library_item' => null,
                'global_duplicate_media_file' => $mediaFile,
                'is_user_duplicate' => true,
                'is_global_duplicate' => true,
                'user_media_file_only' => true,
            ]);

        $strategy = Mockery::mock(SourceStrategyInterface::class);
        $strategy->shouldReceive('getSuccessMessage')->once()->andReturn('Already processed.');

        $processor = new UrlSourceProcessor(
            new LibraryItemFactory,
            $strategy,
            $duplicateProcessor
        );

        $result = $processor->process(
            ['title' => 'Orphan Link', 'description' => 'Desc'],
            'url',
            'https://example.com/orphan.mp3'
        );

        [$libraryItem, $message] = $result;
        expect($libraryItem->media_file_id)->toBe($mediaFile->id);
        expect($libraryItem->title)->toBe('Orphan Link');
    });
});

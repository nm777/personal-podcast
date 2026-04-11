<?php

use App\Services\MediaProcessing\UnifiedDuplicateProcessor;
use App\Services\SourceProcessors\FileUploadProcessor;
use App\Services\SourceProcessors\LibraryItemFactory;
use App\Services\SourceProcessors\UploadStrategy;

describe('FileUploadProcessor', function () {
    it('can be instantiated with dependencies', function () {
        $duplicateProcessor = Mockery::mock(UnifiedDuplicateProcessor::class);
        $libraryItemFactory = new LibraryItemFactory;
        $strategy = new UploadStrategy;

        $processor = new FileUploadProcessor($duplicateProcessor, $libraryItemFactory, $strategy);

        expect($processor)->toBeInstanceOf(FileUploadProcessor::class);
    });

    it('delegates processing message to strategy', function () {
        $duplicateProcessor = Mockery::mock(UnifiedDuplicateProcessor::class);
        $libraryItemFactory = new LibraryItemFactory;
        $strategy = new UploadStrategy;

        expect($strategy->getProcessingMessage())->toBe('Media file uploaded successfully. Processing...');
    });

    it('delegates success messages to strategy', function () {
        $strategy = new UploadStrategy;

        $duplicateMessage = $strategy->getSuccessMessage(true);
        $newFileMessage = $strategy->getSuccessMessage(false);

        expect($duplicateMessage)->toContain('Duplicate file detected');
        expect($duplicateMessage)->toContain(config('constants.duplicate.cleanup_delay_minutes').' minutes.');
        expect($newFileMessage)->toBe('Media file uploaded successfully. Processing...');
    });
});

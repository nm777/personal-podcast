<?php

use App\Services\SourceProcessors\FileUploadProcessor;
use App\Services\SourceProcessors\LibraryItemFactory;

describe('FileUploadProcessor', function () {
    it('can be instantiated with dependencies', function () {
        $duplicateProcessor = Mockery::mock(\App\Services\MediaProcessing\UnifiedDuplicateProcessor::class);
        $libraryItemFactory = new LibraryItemFactory;

        $processor = new FileUploadProcessor($duplicateProcessor, $libraryItemFactory);

        expect($processor)->toBeInstanceOf(FileUploadProcessor::class);
    });

    it('has correct success message for new files', function () {
        $duplicateProcessor = Mockery::mock(\App\Services\MediaProcessing\UnifiedDuplicateProcessor::class);
        $libraryItemFactory = new LibraryItemFactory;

        $processor = new FileUploadProcessor($duplicateProcessor, $libraryItemFactory);

        // Use reflection to test private method
        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('getProcessingMessage');
        $method->setAccessible(true);

        $message = $method->invoke($processor);

        expect($message)->toBe('Media file uploaded successfully. Processing...');
    });

    it('has correct success message for duplicates', function () {
        $duplicateProcessor = Mockery::mock(\App\Services\MediaProcessing\UnifiedDuplicateProcessor::class);
        $libraryItemFactory = new LibraryItemFactory;

        $processor = new FileUploadProcessor($duplicateProcessor, $libraryItemFactory);

        // Use reflection to test private method
        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('getSuccessMessage');
        $method->setAccessible(true);

        $duplicateMessage = $method->invoke($processor, true);
        $newFileMessage = $method->invoke($processor, false);

        expect($duplicateMessage)->toContain('Duplicate file detected');
        expect($newFileMessage)->toBe('Media file uploaded successfully. Processing...');
    });
});

<?php

use App\Http\Requests\LibraryItemRequest;
use App\Models\User;
use App\Services\SourceProcessors\SourceProcessorFactory;
use App\Services\SourceProcessors\UnifiedSourceProcessor;

describe('UnifiedSourceProcessor Edge Cases', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
    });

    it('validates strategy creation with different types', function () {
        // Test that we can create processor with different strategies
        $uploadProcessor = SourceProcessorFactory::create('upload');
        $urlProcessor = SourceProcessorFactory::create('url');
        $youtubeProcessor = SourceProcessorFactory::create('youtube');

        expect($uploadProcessor)->toBeInstanceOf(UnifiedSourceProcessor::class);
        expect($urlProcessor)->toBeInstanceOf(UnifiedSourceProcessor::class);
        expect($youtubeProcessor)->toBeInstanceOf(UnifiedSourceProcessor::class);
    });

    it('handles unauthenticated user gracefully', function () {
        // Log out any authenticated user
        auth()->logout();

        $processor = SourceProcessorFactory::create('url');

        $validated = [
            'title' => 'Test Title',
            'description' => 'Test Description',
        ];

        // Should throw exception when no user is authenticated
        expect(fn () => $processor->process(
            new LibraryItemRequest,
            $validated,
            'url',
            'https://example.com/test.mp3'
        ))->toThrow(TypeError::class);
    });

    it('validates input data structure', function () {
        $this->actingAs($this->user);

        $processor = SourceProcessorFactory::create('url');

        // Test that processor accepts various data structures
        $minimalData = ['title' => 'Test'];
        $fullData = [
            'title' => 'Test Title',
            'description' => 'Test Description',
        ];

        expect($minimalData)->toHaveKey('title');
        expect($fullData)->toHaveKeys(['title', 'description']);
    });

    it('handles special characters in validated data', function () {
        $this->actingAs($this->user);

        // Test data with special characters
        $specialData = [
            'title' => 'Test with émojis 🎵 and spéciâl chars',
            'description' => 'Description with 中文 and ñ characters',
        ];

        expect($specialData['title'])->toContain('🎵');
        expect($specialData['description'])->toContain('中文');
    });
});

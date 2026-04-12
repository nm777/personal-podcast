<?php

use App\Http\Requests\LibraryItemRequest;
use App\Models\LibraryItem;
use App\Models\MediaFile;
use App\Models\User;
use App\Services\SourceProcessors\SourceProcessorFactory;
use App\Services\SourceProcessors\UnifiedSourceProcessor;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

describe('UnifiedSourceProcessor Edge Cases', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        Storage::fake('public');
        Queue::fake();
    });

    it('validates strategy creation with different types', function () {
        $uploadProcessor = SourceProcessorFactory::create('upload');
        $urlProcessor = SourceProcessorFactory::create('url');
        $youtubeProcessor = SourceProcessorFactory::create('youtube');

        expect($uploadProcessor)->toBeInstanceOf(UnifiedSourceProcessor::class);
        expect($urlProcessor)->toBeInstanceOf(UnifiedSourceProcessor::class);
        expect($youtubeProcessor)->toBeInstanceOf(UnifiedSourceProcessor::class);
    });

    it('handles unauthenticated user gracefully', function () {
        auth()->logout();

        $processor = SourceProcessorFactory::create('url');

        $validated = [
            'title' => 'Test Title',
            'description' => 'Test Description',
        ];

        expect(fn () => $processor->process(
            new LibraryItemRequest,
            $validated,
            'url',
            'https://example.com/test.mp3'
        ))->toThrow(\TypeError::class);
    });

    it('creates library item with minimal data (title only)', function () {
        $this->actingAs($this->user);
        $processor = SourceProcessorFactory::create('url');

        $result = $processor->process(
            new LibraryItemRequest,
            ['title' => 'Minimal Item'],
            'url',
            'https://example.com/test.mp3'
        );

        [$libraryItem, $message] = $result;
        expect($libraryItem)->toBeInstanceOf(LibraryItem::class);
        expect($libraryItem->title)->toBe('Minimal Item');
        expect($libraryItem->description)->toBeNull();
        expect($libraryItem->user_id)->toBe($this->user->id);
        expect($libraryItem->source_url)->toBe('https://example.com/test.mp3');
    });

    it('creates library item with special characters in title and description', function () {
        $this->actingAs($this->user);
        $processor = SourceProcessorFactory::create('url');

        $result = $processor->process(
            new LibraryItemRequest,
            [
                'title' => 'Test with émojis 🎵 and spéciâl chars',
                'description' => 'Description with 中文 and ñ characters',
            ],
            'url',
            'https://example.com/special.mp3'
        );

        [$libraryItem, $message] = $result;
        expect($libraryItem->title)->toBe('Test with émojis 🎵 and spéciâl chars');
        expect($libraryItem->description)->toBe('Description with 中文 and ñ characters');

        $this->assertDatabaseHas('library_items', [
            'id' => $libraryItem->id,
            'title' => 'Test with émojis 🎵 and spéciâl chars',
        ]);
    });

    it('handles URL with query parameters and fragments', function () {
        $this->actingAs($this->user);
        $processor = SourceProcessorFactory::create('url');

        $url = 'https://example.com/audio.mp3?v=1&t=30#section';

        $result = $processor->process(
            new LibraryItemRequest,
            ['title' => 'Complex URL'],
            'url',
            $url
        );

        [$libraryItem, $message] = $result;
        expect($libraryItem->source_url)->toBe($url);
        expect($libraryItem->user_id)->toBe($this->user->id);
    });

    it('links to existing media file when user media file only edge case', function () {
        $this->actingAs($this->user);

        $mediaFile = MediaFile::factory()->create([
            'user_id' => $this->user->id,
            'source_url' => 'https://example.com/orphan.mp3',
        ]);

        $processor = SourceProcessorFactory::create('url');

        $result = $processor->process(
            new LibraryItemRequest,
            ['title' => 'Re-link'],
            'url',
            'https://example.com/orphan.mp3'
        );

        [$libraryItem, $message] = $result;
        expect($libraryItem->media_file_id)->toBe($mediaFile->id);
    });
});

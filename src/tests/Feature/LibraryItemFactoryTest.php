<?php

use App\Enums\ProcessingStatusType;
use App\Models\LibraryItem;
use App\Models\MediaFile;
use App\Models\User;
use App\Services\SourceProcessors\LibraryItemFactory;

describe('LibraryItemFactory', function () {
    beforeEach(function () {
        $this->factory = new LibraryItemFactory;
        $this->user = User::factory()->create();
    });

    it('creates library item from validated data with all fields', function () {
        $item = $this->factory->createFromValidated(
            ['title' => 'My Podcast', 'description' => 'A great episode'],
            'url',
            'https://example.com/audio.mp3',
            $this->user->id
        );

        expect($item)->toBeInstanceOf(LibraryItem::class);
        expect($item->title)->toBe('My Podcast');
        expect($item->description)->toBe('A great episode');
        expect($item->source_type)->toBe('url');
        expect($item->source_url)->toBe('https://example.com/audio.mp3');
        expect($item->user_id)->toBe($this->user->id);
        expect($item->processing_status)->toBe(ProcessingStatusType::PENDING);
        $this->assertDatabaseHas('library_items', ['id' => $item->id, 'title' => 'My Podcast']);
    });

    it('creates library item with minimal data', function () {
        $item = $this->factory->createFromValidated(
            ['title' => 'Minimal'],
            'upload',
            null,
            $this->user->id
        );

        expect($item->title)->toBe('Minimal');
        expect($item->description)->toBeNull();
        expect($item->source_url)->toBeNull();
        $this->assertDatabaseHas('library_items', ['id' => $item->id]);
    });

    it('creates library item with media file data merged in', function () {
        $item = $this->factory->createFromValidatedWithMediaData(
            ['title' => 'With Media'],
            'upload',
            ['file_path' => 'uploads/test.mp3', 'file_hash' => 'abc123', 'mime_type' => 'audio/mpeg', 'filesize' => 1024],
            $this->user->id
        );

        expect($item)->toBeInstanceOf(LibraryItem::class);
        expect($item->title)->toBe('With Media');
        expect($item->source_type)->toBe('upload');
        $this->assertDatabaseHas('library_items', ['id' => $item->id, 'title' => 'With Media']);
    });

    it('creates library item linked to existing media file', function () {
        $mediaFile = MediaFile::factory()->create(['user_id' => $this->user->id]);

        $item = $this->factory->createFromValidatedWithMediaFile(
            $mediaFile,
            ['title' => 'Linked'],
            'url',
            'https://example.com/audio.mp3',
            $this->user->id
        );

        expect($item->media_file_id)->toBe($mediaFile->id);
        expect($item->processing_status)->toBe(ProcessingStatusType::COMPLETED);
        expect($item->processing_completed_at)->not->toBeNull();
        $this->assertDatabaseHas('library_items', ['id' => $item->id, 'media_file_id' => $mediaFile->id]);
    });

    it('marks as duplicate when media file belongs to same user', function () {
        $mediaFile = MediaFile::factory()->create(['user_id' => $this->user->id]);

        $item = $this->factory->createFromValidatedWithMediaFile(
            $mediaFile,
            ['title' => 'Dup'],
            'url',
            null,
            $this->user->id
        );

        expect($item->is_duplicate)->toBeTrue();
        expect($item->duplicate_detected_at)->not->toBeNull();
    });

    it('does not mark as duplicate when media file belongs to different user', function () {
        $otherUser = User::factory()->create();
        $mediaFile = MediaFile::factory()->create(['user_id' => $otherUser->id]);

        $item = $this->factory->createFromValidatedWithMediaFile(
            $mediaFile,
            ['title' => 'Cross-user'],
            'url',
            null,
            $this->user->id
        );

        expect($item->is_duplicate)->toBeFalse();
        expect($item->duplicate_detected_at)->toBeNull();
    });

    it('requires validated array as first argument', function () {
        expect(fn () => $this->factory->createFromValidated('invalid', 'upload'))
            ->toThrow(TypeError::class);
    });

    it('sets pending status for new items', function () {
        $item = $this->factory->createFromValidated(
            ['title' => 'Pending Test'],
            'upload',
            null,
            $this->user->id
        );

        expect($item->processing_status)->toBe(ProcessingStatusType::PENDING);
        expect($item->processing_completed_at)->toBeNull();
    });

    it('uses authenticated user when userId is null', function () {
        $this->actingAs($this->user);

        $item = $this->factory->createFromValidated(
            ['title' => 'Auth User'],
            'upload'
        );

        expect($item->user_id)->toBe($this->user->id);
    });
});

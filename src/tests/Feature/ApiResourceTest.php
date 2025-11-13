<?php

use App\Http\Resources\FeedResource;
use App\Http\Resources\LibraryItemResource;
use App\Http\Resources\MediaFileResource;
use App\Models\Feed;
use App\Models\LibraryItem;
use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('API Resources', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
    });

    it('transforms library item correctly', function () {
        $libraryItem = LibraryItem::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Test Item',
            'description' => 'Test Description',
            'source_type' => 'url',
            'source_url' => 'https://example.com/test.mp3',
        ]);

        $resource = LibraryItemResource::make($libraryItem);
        $array = $resource->toArray(request());

        expect($array)->toHaveKey('id');
        expect($array)->toHaveKey('title');
        expect($array)->toHaveKey('description');
        expect($array)->toHaveKey('source_type');
        expect($array)->toHaveKey('source_url');
        expect($array)->toHaveKey('is_duplicate');
        expect($array)->toHaveKey('processing_status');
        expect($array)->toHaveKey('is_processing');
        expect($array['title'])->toBe('Test Item');
        expect($array['source_type'])->toBe('url');
    });

    it('transforms feed correctly', function () {
        $feed = Feed::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Test Feed',
            'description' => 'Test Feed Description',
            'is_public' => true,
            'slug' => 'test-feed',
        ]);

        $resource = FeedResource::make($feed);
        $array = $resource->toArray(request());

        expect($array)->toHaveKey('id');
        expect($array)->toHaveKey('title');
        expect($array)->toHaveKey('description');
        expect($array)->toHaveKey('is_public');
        expect($array)->toHaveKey('slug');
        expect($array['title'])->toBe('Test Feed');
        expect($array['is_public'])->toBeTrue();
    });

    it('transforms media file correctly', function () {
        $mediaFile = MediaFile::factory()->create([
            'user_id' => $this->user->id,
            'file_path' => 'test/path.mp3',
            'file_hash' => 'abc123',
            'mime_type' => 'audio/mpeg',
            'filesize' => 1024,
        ]);

        $resource = MediaFileResource::make($mediaFile);
        $array = $resource->toArray(request());

        expect($array)->toHaveKey('id');
        expect($array)->toHaveKey('file_path');
        expect($array)->toHaveKey('public_url');
        expect($array)->toHaveKey('file_hash');
        expect($array)->toHaveKey('mime_type');
        expect($array)->toHaveKey('filesize');
        expect($array['mime_type'])->toBe('audio/mpeg');
        expect($array['filesize'])->toBe(1024);
    });

    it('handles null relationships correctly', function () {
        $libraryItem = LibraryItem::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Test Item',
            'media_file_id' => null, // No associated media file
        ]);

        $resource = LibraryItemResource::make($libraryItem);
        $array = $resource->toArray(request());

        // The when() method should handle null relationships gracefully
        // MissingValue objects are converted to null during JSON serialization
        expect($array)->toHaveKey('media_file');
        expect($array['media_file'])->toBeInstanceOf(\Illuminate\Http\Resources\MissingValue::class);
    });
});

<?php

use App\Enums\ProcessingStatusType;
use App\Jobs\ProcessMediaFile;
use App\Models\LibraryItem;
use App\Models\MediaFile;
use App\Models\User;
use App\Services\MediaProcessing\MediaProcessingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

describe('media_file_id persistence', function () {
    beforeEach(function () {
        Storage::fake('public');
        Queue::fake();
        $this->user = User::factory()->create();
    });

    it('persists media_file_id after processing a URL download', function () {
        $mp3Content = 'ID3'.str_repeat("\x00", 100);

        Http::fake([
            'https://example.com/test-persist.mp3' => Http::response($mp3Content, 200, [
                'Content-Type' => 'audio/mpeg',
            ]),
        ]);

        $libraryItem = LibraryItem::factory()->create([
            'user_id' => $this->user->id,
            'source_type' => 'url',
            'source_url' => 'https://example.com/test-persist.mp3',
            'processing_status' => ProcessingStatusType::PENDING,
        ]);

        $job = new ProcessMediaFile($libraryItem, 'https://example.com/test-persist.mp3', null);
        $job->handle(app(MediaProcessingService::class));

        $libraryItem->refresh();

        expect($libraryItem->processing_status)->toBe(ProcessingStatusType::COMPLETED);
        expect($libraryItem->media_file_id)->not->toBeNull();

        $fromDb = LibraryItem::find($libraryItem->id);
        expect($fromDb->media_file_id)->not->toBeNull();
        expect($fromDb->media_file_id)->toBe($libraryItem->media_file_id);
    });

    it('persists media_file_id after processing a file upload', function () {
        $content = 'ID3'.str_repeat("\x00", 100);
        $tempPath = 'temp-uploads/test-upload.mp3';
        Storage::disk('public')->put($tempPath, $content);

        $libraryItem = LibraryItem::factory()->create([
            'user_id' => $this->user->id,
            'source_type' => 'upload',
            'processing_status' => ProcessingStatusType::PENDING,
        ]);

        $service = app(MediaProcessingService::class);
        $result = $service->processFromFile($libraryItem, $tempPath);

        expect($result['media_file'])->not->toBeNull();

        $fromDb = LibraryItem::find($libraryItem->id);
        expect($fromDb->media_file_id)->not->toBeNull();
        expect($fromDb->media_file_id)->toBe($result['media_file']->id);
    });

    it('persists media_file_id when linking to user duplicate', function () {
        $content = 'ID3'.str_repeat("\x00", 100);
        $hash = hash('sha256', $content);

        $existingMediaFile = MediaFile::factory()->create([
            'user_id' => $this->user->id,
            'file_hash' => $hash,
        ]);

        LibraryItem::factory()->create([
            'user_id' => $this->user->id,
            'media_file_id' => $existingMediaFile->id,
        ]);

        $tempPath = 'temp-uploads/dup-upload.mp3';
        Storage::disk('public')->put($tempPath, $content);

        $libraryItem = LibraryItem::factory()->create([
            'user_id' => $this->user->id,
            'source_type' => 'upload',
            'processing_status' => ProcessingStatusType::PENDING,
        ]);

        $service = app(MediaProcessingService::class);
        $result = $service->processFromFile($libraryItem, $tempPath);

        expect($result['media_file'])->not->toBeNull();
        expect($result['is_duplicate'])->toBeTrue();

        $fromDb = LibraryItem::find($libraryItem->id);
        expect($fromDb->media_file_id)->not->toBeNull();
        expect($fromDb->media_file_id)->toBe($existingMediaFile->id);
    });

    it('persists media_file_id when linking to global duplicate', function () {
        $content = 'ID3'.str_repeat("\x00", 100);
        $hash = hash('sha256', $content);

        $otherUser = User::factory()->create();
        $existingMediaFile = MediaFile::factory()->create([
            'user_id' => $otherUser->id,
            'file_hash' => $hash,
        ]);

        LibraryItem::factory()->create([
            'user_id' => $otherUser->id,
            'media_file_id' => $existingMediaFile->id,
        ]);

        $tempPath = 'temp-uploads/global-dup.mp3';
        Storage::disk('public')->put($tempPath, $content);

        $libraryItem = LibraryItem::factory()->create([
            'user_id' => $this->user->id,
            'source_type' => 'upload',
            'processing_status' => ProcessingStatusType::PENDING,
        ]);

        $service = app(MediaProcessingService::class);
        $result = $service->processFromFile($libraryItem, $tempPath);

        expect($result['media_file'])->not->toBeNull();

        $fromDb = LibraryItem::find($libraryItem->id);
        expect($fromDb->media_file_id)->not->toBeNull();
        expect($fromDb->media_file_id)->toBe($existingMediaFile->id);
    });
});

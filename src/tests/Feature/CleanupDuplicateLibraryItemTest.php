<?php

use App\Jobs\CleanupDuplicateLibraryItem;
use App\Models\LibraryItem;
use App\Models\MediaFile;
use App\Models\User;
use App\ProcessingStatusType;

it('deletes a duplicate library item', function () {
    $user = User::factory()->create();
    $mediaFile = MediaFile::factory()->create(['user_id' => $user->id]);

    $item = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
        'is_duplicate' => true,
        'processing_status' => ProcessingStatusType::COMPLETED,
    ]);

    $job = new CleanupDuplicateLibraryItem($item);
    $job->handle();

    expect(LibraryItem::find($item->id))->toBeNull();
});

it('skips non-duplicate library items', function () {
    $user = User::factory()->create();
    $mediaFile = MediaFile::factory()->create(['user_id' => $user->id]);

    $item = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
        'is_duplicate' => false,
        'processing_status' => ProcessingStatusType::COMPLETED,
    ]);

    $job = new CleanupDuplicateLibraryItem($item);
    $job->handle();

    expect(LibraryItem::find($item->id))->not->toBeNull();
});

it('handles already-deleted items gracefully', function () {
    $user = User::factory()->create();
    $mediaFile = MediaFile::factory()->create(['user_id' => $user->id]);

    $item = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
        'is_duplicate' => true,
        'processing_status' => ProcessingStatusType::COMPLETED,
    ]);

    $item->delete();

    $job = new CleanupDuplicateLibraryItem($item);
    $job->handle();

    expect(LibraryItem::find($item->id))->toBeNull();
});

<?php

use App\Models\LibraryItem;
use App\Models\MediaFile;
use App\Models\User;

it('MediaFile factory creates valid record with user_id', function () {
    $mediaFile = MediaFile::factory()->create();

    expect($mediaFile->user_id)->not->toBeNull();
    expect($mediaFile->user_id)->toBeInt();
    expect($mediaFile->file_path)->not->toBeEmpty();
    expect($mediaFile->file_hash)->not->toBeEmpty();
});

it('LibraryItem factory creates record with processing_status and is_duplicate', function () {
    $item = LibraryItem::factory()->create();

    expect($item->processing_status)->not->toBeNull();
    expect($item->is_duplicate)->toBeBool();
    expect($item->user_id)->not->toBeNull();
});

it('LibraryItem factory defaults to completed processing status', function () {
    $item = LibraryItem::factory()->create();

    expect($item->processing_status)->toBe(\App\ProcessingStatusType::COMPLETED);
    expect($item->is_duplicate)->toBeFalse();
});

it('LibraryItem factory can create item without media file', function () {
    $item = LibraryItem::factory()->create(['media_file_id' => null]);

    expect($item->media_file_id)->toBeNull();
    expect($item->processing_status)->not->toBeNull();
});

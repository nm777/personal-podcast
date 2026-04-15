<?php

use App\Http\Resources\LibraryItemResource;
use App\Http\Resources\MediaFileResource;
use App\Models\LibraryItem;
use App\Models\MediaFile;

it('media file resource includes public_url but not file_path', function () {
    $mediaFile = MediaFile::factory()->create();

    $resource = new MediaFileResource($mediaFile);
    $data = $resource->resolve();

    expect($data)->not->toHaveKey('file_path');
    expect($data)->toHaveKey('public_url');
    expect($data['public_url'])->toBe($mediaFile->public_url);
});

it('library item resource does not leak file_path through nested media_file', function () {
    $mediaFile = MediaFile::factory()->create();
    $item = LibraryItem::factory()->create([
        'media_file_id' => $mediaFile->id,
    ]);

    $resource = new LibraryItemResource($item);
    $item->load('mediaFile');
    $data = (new LibraryItemResource($item))->resolve();

    expect($data)->toHaveKey('media_file');
    $mediaFileData = $data['media_file']->resolve();
    expect($mediaFileData)->not->toHaveKey('file_path');
    expect($mediaFileData)->toHaveKey('public_url');
});

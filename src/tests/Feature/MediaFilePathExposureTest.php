<?php

use App\Models\MediaFile;

it('media file resource includes public_url but not file_path', function () {
    $mediaFile = MediaFile::factory()->create();

    $resource = new \App\Http\Resources\MediaFileResource($mediaFile);
    $data = $resource->resolve();

    expect($data)->not->toHaveKey('file_path');
    expect($data)->toHaveKey('public_url');
    expect($data['public_url'])->toBe($mediaFile->public_url);
});

it('library item resource does not leak file_path through nested media_file', function () {
    $mediaFile = MediaFile::factory()->create();
    $item = \App\Models\LibraryItem::factory()->create([
        'media_file_id' => $mediaFile->id,
    ]);

    $resource = new \App\Http\Resources\LibraryItemResource($item);
    $item->load('mediaFile');
    $data = (new \App\Http\Resources\LibraryItemResource($item))->resolve();

    expect($data)->toHaveKey('media_file');
    $mediaFileData = $data['media_file']->resolve();
    expect($mediaFileData)->not->toHaveKey('file_path');
    expect($mediaFileData)->toHaveKey('public_url');
});

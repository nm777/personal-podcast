<?php

use App\Models\LibraryItem;
use App\Models\MediaFile;
use App\Models\User;

describe('cross-user dedup cascade safety', function () {
    it('does not cascade delete other users library items when media file owner deletes their item', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $mediaFile = MediaFile::factory()->create([
            'user_id' => $user1->id,
            'file_hash' => 'shared-hash-abc123',
        ]);

        $item1 = LibraryItem::factory()->create([
            'user_id' => $user1->id,
            'media_file_id' => $mediaFile->id,
        ]);

        $item2 = LibraryItem::factory()->create([
            'user_id' => $user2->id,
            'media_file_id' => $mediaFile->id,
        ]);

        $item1->delete();

        expect(LibraryItem::find($item2->id))->not->toBeNull();
        expect(MediaFile::find($mediaFile->id))->not->toBeNull();
    });

    it('sets media_file_id to null instead of cascade deleting when media file is deleted', function () {
        $user = User::factory()->create();

        $mediaFile = MediaFile::factory()->create([
            'user_id' => $user->id,
        ]);

        $item = LibraryItem::factory()->create([
            'user_id' => $user->id,
            'media_file_id' => $mediaFile->id,
        ]);

        $mediaFile->delete();

        $item->refresh();
        expect($item->media_file_id)->toBeNull();
        expect(LibraryItem::find($item->id))->not->toBeNull();
    });
});

<?php

use App\Models\LibraryItem;
use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

it('can delete a library item', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $mediaFile = MediaFile::factory()->create();

    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
    ]);

    $response = $this->actingAs($user)->delete("/library/{$libraryItem->id}");

    $response->assertRedirect('/library');
    $response->assertSessionHas('success');

    $this->assertDatabaseMissing('library_items', [
        'id' => $libraryItem->id,
    ]);
});

it('cannot delete another user library item', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $mediaFile = MediaFile::factory()->create();

    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $otherUser->id,
        'media_file_id' => $mediaFile->id,
    ]);

    $response = $this->actingAs($user)->delete("/library/{$libraryItem->id}");

    $response->assertForbidden();
});

it('deletes orphaned media files when last library item is removed', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $mediaFile = MediaFile::factory()->create([
        'file_path' => 'media/test-file.mp3',
    ]);

    Storage::disk('public')->put($mediaFile->file_path, 'fake content');

    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
    ]);

    $this->actingAs($user)->delete("/library/{$libraryItem->id}");

    $this->assertDatabaseMissing('media_files', [
        'id' => $mediaFile->id,
    ]);

    Storage::disk('public')->assertMissing($mediaFile->file_path);
});

it('keeps media files when other users still reference them', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $mediaFile = MediaFile::factory()->create([
        'file_path' => 'media/shared-file.mp3',
    ]);

    Storage::disk('public')->put($mediaFile->file_path, 'fake content');

    $userItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
    ]);

    $otherUserItem = LibraryItem::factory()->create([
        'user_id' => $otherUser->id,
        'media_file_id' => $mediaFile->id,
    ]);

    $this->actingAs($user)->delete("/library/{$userItem->id}");

    $this->assertDatabaseHas('media_files', [
        'id' => $mediaFile->id,
    ]);

    Storage::disk('public')->assertExists($mediaFile->file_path);
});

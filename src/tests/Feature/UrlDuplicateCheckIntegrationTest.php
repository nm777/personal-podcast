<?php

use App\Models\LibraryItem;
use App\Models\MediaFile;
use App\Models\User;

test('URL duplicate checking works end-to-end', function () {
    $user = User::factory()->create();

    // Create existing library item with media file
    $mediaFile = MediaFile::factory()->create([
        'user_id' => $user->id,
        'source_url' => 'https://example.com/existing-audio.mp3',
    ]);
    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
        'source_url' => 'https://example.com/existing-audio.mp3',
    ]);

    // Test duplicate detection
    $response = $this->actingAs($user)->postJson('/check-url-duplicate', [
        'url' => 'https://example.com/existing-audio.mp3',
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'is_duplicate' => true,
        'existing_file' => [
            'id' => $mediaFile->id,
            'mime_type' => $mediaFile->mime_type,
            'filesize' => $mediaFile->filesize,
        ],
    ]);

    // Test non-duplicate detection
    $response = $this->actingAs($user)->postJson('/check-url-duplicate', [
        'url' => 'https://example.com/new-audio.mp3',
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'is_duplicate' => false,
        'existing_file' => null,
    ]);
});

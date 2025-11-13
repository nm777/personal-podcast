<?php

use App\Models\LibraryItem;
use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

it('can add media file from URL', function () {
    Storage::fake('public');
    Queue::fake();

    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/library', [
        'title' => 'Test URL Audio',
        'description' => 'Test Description from URL',
        'url' => 'https://example.com/test-audio.mp3',
    ]);

    $response->assertRedirect('/library');
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('library_items', [
        'user_id' => $user->id,
        'title' => 'Test URL Audio',
        'description' => 'Test Description from URL',
        'source_type' => 'url',
        'source_url' => 'https://example.com/test-audio.mp3',
    ]);

    Queue::assertPushed(\App\Jobs\ProcessMediaFile::class);
});

it('validates URL requirements', function () {
    $user = User::factory()->create();

    // Test missing both file and URL
    $response = $this->actingAs($user)->post('/library', [
        'title' => 'Test Audio',
    ]);

    $response->assertSessionHasErrors(['file', 'url']);

    // Test invalid URL format
    $response = $this->actingAs($user)->post('/library', [
        'title' => 'Test Audio',
        'url' => 'not-a-valid-url',
    ]);

    $response->assertSessionHasErrors('url');
});

it('processes media file from URL correctly', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'source_type' => 'url',
        'source_url' => 'https://example.com/test-audio.mp3',
    ]);

    // Mock HTTP response
    Http::fake([
        'https://example.com/test-audio.mp3' => Http::response('fake audio content', 200, [
            'Content-Type' => 'audio/mpeg',
        ]),
    ]);

    $job = new \App\Jobs\ProcessMediaFile($libraryItem, 'https://example.com/test-audio.mp3', null);
    $job->handle();

    $libraryItem->refresh();

    expect($libraryItem->media_file_id)->not->toBeNull();

    $mediaFile = $libraryItem->mediaFile;
    expect($mediaFile)->not->toBeNull();
    expect($mediaFile->file_hash)->toBe(hash('sha256', 'fake audio content'));
    // MIME type is detected from file extension by the system
    expect($mediaFile->mime_type)->toBe('application/octet-stream'); // New validator returns octet-stream for unknown content
});

it('handles URL download failures gracefully', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'source_type' => 'url',
        'source_url' => 'https://example.com/not-found.mp3',
    ]);

    // Mock failed HTTP response
    Http::fake([
        'https://example.com/not-found.mp3' => Http::response('Not Found', 404),
    ]);

    $job = new \App\Jobs\ProcessMediaFile($libraryItem, 'https://example.com/not-found.mp3', null);
    $job->handle();

    // Library item should be marked as failed
    $this->assertDatabaseHas('library_items', [
        'id' => $libraryItem->id,
        'processing_status' => \App\ProcessingStatusType::FAILED->value,
    ]);
});

it('handles JavaScript redirect pages correctly', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'source_type' => 'url',
        'source_url' => 'https://file-examples.com/wp-content/storage/2017/11/file_example_MP3_700KB.mp3',
    ]);

    $htmlRedirectPage = '<!DOCTYPE html><html><head><title>Redirect</title></head><body><script>window.location.replace("https://file-examples.com/storage/fef7fa310369115b497def4/file_example_MP3_700KB.mp3");</script></body></html>';
    $mp3Content = 'ID3fake audio content';

    // Mock the redirect page and the final MP3 file
    Http::fake([
        'https://file-examples.com/wp-content/storage/2017/11/file_example_MP3_700KB.mp3' => Http::response($htmlRedirectPage, 200),
        'https://file-examples.com/storage/fef7fa310369115b497def4/file_example_MP3_700KB.mp3' => Http::response($mp3Content, 200, [
            'Content-Type' => 'audio/mpeg',
        ]),
    ]);

    $job = new \App\Jobs\ProcessMediaFile($libraryItem, 'https://file-examples.com/wp-content/storage/2017/11/file_example_MP3_700KB.mp3', null);
    $job->handle();

    $libraryItem->refresh();

    expect($libraryItem->processing_status)->toBe(\App\ProcessingStatusType::COMPLETED);
    expect($libraryItem->media_file_id)->not->toBeNull();

    $mediaFile = $libraryItem->mediaFile;
    expect($mediaFile->file_hash)->toBe(hash('sha256', $mp3Content));
});

it('fails when JavaScript redirect cannot be resolved', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'source_type' => 'url',
        'source_url' => 'https://example.com/redirect.mp3',
    ]);

    $htmlRedirectPage = '<!DOCTYPE html><html><head><title>Redirect</title></head><body><script>window.location.replace("https://example.com/final.mp3");</script></body></html>';

    // Mock of redirect page but fail to final request
    Http::fake([
        'https://example.com/redirect.mp3' => Http::response($htmlRedirectPage, 200),
        'https://example.com/final.mp3' => Http::response('Not Found', 404),
    ]);

    $job = new \App\Jobs\ProcessMediaFile($libraryItem, 'https://example.com/redirect.mp3', null);
    $job->handle();

    $libraryItem->refresh();

    expect($libraryItem->processing_status)->toBe(\App\ProcessingStatusType::FAILED);
    expect($libraryItem->processing_error)->toContain('Got HTML redirect page instead of media file');
});

it('handles file-examples.com JavaScript redirect pattern correctly', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'source_type' => 'url',
        'source_url' => 'https://file-examples.com/wp-content/storage/2017/11/file_example_MP3_700KB.mp3',
    ]);

    // Simulate the exact HTML redirect pattern from file-examples.com
    $htmlRedirectPage = '<!DOCTYPE html><html><head><title>File Examples | Download redirect...</title></head><body><script>document.addEventListener(\'DOMContentLoaded\', function(){setTimeout(function (){url=window.location.href.replace(\'file-examples.com/wp-content/storage/\',\'file-examples.com/storage/fef7fa310369115b497def4/\'); window.location.replace(url);}, 3000);}, false);</script></body></html>';

    $mp3Content = 'ID3'.str_repeat('x', 100); // Valid MP3 content with ID3 tag

    // Mock the redirect page and final MP3 file
    Http::fake([
        'https://file-examples.com/wp-content/storage/2017/11/file_example_MP3_700KB.mp3' => Http::response($htmlRedirectPage, 200),
        'https://file-examples.com/storage/fef7fa310369115b497def4/2017/11/file_example_MP3_700KB.mp3' => Http::response($mp3Content, 200, [
            'Content-Type' => 'audio/mpeg',
        ]),
    ]);

    $job = new \App\Jobs\ProcessMediaFile($libraryItem, 'https://file-examples.com/wp-content/storage/2017/11/file_example_MP3_700KB.mp3', null);
    $job->handle();

    $libraryItem->refresh();

    expect($libraryItem->processing_status)->toBe(\App\ProcessingStatusType::COMPLETED);
    expect($libraryItem->media_file_id)->not->toBeNull();

    $mediaFile = $libraryItem->mediaFile;
    expect($mediaFile->file_hash)->toBe(hash('sha256', $mp3Content));
    expect($mediaFile->filesize)->toBe(strlen($mp3Content));
    // Storage::fake() returns text/plain for all files, so we check that it's not HTML
    expect($mediaFile->mime_type)->not->toBe('text/html');
});

it('reuses existing media file when same URL is provided', function () {
    Storage::fake('public');
    Queue::fake();

    $user = User::factory()->create();
    $mediaFile = MediaFile::factory()->create([
        'user_id' => $user->id,
        'source_url' => 'https://example.com/shared-audio.mp3',
    ]);
    $existingLibraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
        'source_type' => 'url',
        'source_url' => 'https://example.com/shared-audio.mp3',
    ]);

    // First user adds URL
    $response = $this->actingAs($user)->post('/library', [
        'title' => 'First Copy',
        'url' => 'https://example.com/shared-audio.mp3',
    ]);

    $response->assertRedirect('/library');
    $response->assertSessionHas('success', 'This URL has already been processed. The existing media file has been linked to this library item.');

    $this->assertDatabaseHas('library_items', [
        'user_id' => $user->id,
        'title' => 'First Copy',
        'source_url' => 'https://example.com/shared-audio.mp3',
        'media_file_id' => $mediaFile->id,
    ]);

    // Should not schedule cleanup - duplicates are now linked immediately
    Queue::assertNotPushed(\App\Jobs\CleanupDuplicateLibraryItem::class);
});

it('does not reuse files when URLs are different', function () {
    Storage::fake('public');
    Queue::fake();

    $user = User::factory()->create();
    $mediaFile = MediaFile::factory()->create([
        'source_url' => 'https://example.com/different-audio.mp3',
    ]);

    // User adds a different URL
    $response = $this->actingAs($user)->post('/library', [
        'title' => 'Different Audio',
        'url' => 'https://example.com/shared-audio.mp3',
    ]);

    $response->assertRedirect('/library');
    $response->assertSessionHas('success', 'URL added successfully. Processing...');

    $this->assertDatabaseHas('library_items', [
        'user_id' => $user->id,
        'title' => 'Different Audio',
        'source_url' => 'https://example.com/shared-audio.mp3',
        'media_file_id' => null, // Will be set by job
    ]);

    // Job should be dispatched since URL is different
    Queue::assertPushed(\App\Jobs\ProcessMediaFile::class);
});

it('stores source URL when downloading new file', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'source_type' => 'url',
        'source_url' => 'https://example.com/new-audio.mp3',
    ]);

    // Mock HTTP response
    Http::fake([
        'https://example.com/new-audio.mp3' => Http::response('new audio content', 200, [
            'Content-Type' => 'audio/mpeg',
        ]),
    ]);

    $job = new \App\Jobs\ProcessMediaFile($libraryItem, 'https://example.com/new-audio.mp3', null);
    $job->handle();

    $libraryItem->refresh();

    expect($libraryItem->media_file_id)->not->toBeNull();

    $mediaFile = $libraryItem->mediaFile;
    expect($mediaFile)->not->toBeNull();
    expect($mediaFile->source_url)->toBe('https://example.com/new-audio.mp3');
});

it('multiple users can reuse same file from same URL', function () {
    Storage::fake('public');
    Queue::fake();

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    // First user downloads the file
    Http::fake([
        'https://example.com/shared-audio.mp3' => Http::response('shared audio content', 200),
    ]);

    $response1 = $this->actingAs($user1)->post('/library', [
        'title' => 'User 1 Copy',
        'url' => 'https://example.com/shared-audio.mp3',
    ]);

    $response1->assertRedirect('/library');
    Queue::assertPushed(\App\Jobs\ProcessMediaFile::class);

    // Process the job manually to create the media file
    $libraryItem = LibraryItem::where('title', 'User 1 Copy')->first();
    $job = new \App\Jobs\ProcessMediaFile($libraryItem, 'https://example.com/shared-audio.mp3', null);
    $job->handle();

    $mediaFile = MediaFile::where('source_url', 'https://example.com/shared-audio.mp3')->first();
    expect($mediaFile)->not->toBeNull();

    // Second user adds the same URL
    Queue::fake(); // Reset queue fake
    $response2 = $this->actingAs($user2)->post('/library', [
        'title' => 'User 2 Copy',
        'url' => 'https://example.com/shared-audio.mp3',
    ]);

    $response2->assertRedirect('/library');
    $response2->assertSessionHas('success', 'This URL has already been processed. The existing media file has been linked to this library item.');

    // No new job should be dispatched since we reuse the existing media file
    Queue::assertNotPushed(\App\Jobs\ProcessMediaFile::class);

    // User1 should have library item pointing to their media file
    $this->assertDatabaseHas('library_items', [
        'user_id' => $user1->id,
        'media_file_id' => $mediaFile->id,
    ]);

    // User2 should have their own library item with their own media file (cross-user deduplication)
    $user2LibraryItem = \App\Models\LibraryItem::where('user_id', $user2->id)
        ->where('title', 'User 2 Copy')
        ->first();
    expect($user2LibraryItem)->not->toBeNull();
    expect($user2LibraryItem->media_file_id)->toBe($mediaFile->id); // Should be same - we reuse media files
    expect($user2LibraryItem->is_duplicate)->toBeTrue(); // Cross-user links are now marked as duplicates
});

<?php

use App\Models\Feed;
use App\Models\FeedItem;
use App\Models\LibraryItem;
use App\Models\MediaFile;
use App\Models\User;
use App\ProcessingStatusType;
use Illuminate\Support\Facades\Storage;

it('includes published_at in library index inertia data', function () {
    $user = User::factory()->create();
    $mediaFile = MediaFile::factory()->create();

    LibraryItem::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
        'title' => 'Test Item',
        'published_at' => '2025-06-15 10:30:00',
    ]);

    $response = $this->actingAs($user)->get('/library');

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page->component('Library/Index')
            ->has('libraryItems', 1)
            ->where('libraryItems.0.published_at', fn ($value) => str_starts_with($value, '2025-06-15T10:30:00'))
    );
});

it('returns null published_at when not set', function () {
    $user = User::factory()->create();
    $mediaFile = MediaFile::factory()->create();

    LibraryItem::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
        'title' => 'Test Item',
    ]);

    $response = $this->actingAs($user)->get('/library');

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page->component('Library/Index')
            ->where('libraryItems.0.published_at', null)
    );
});

it('allows updating published_at via edit endpoint', function () {
    $user = User::factory()->create();
    $mediaFile = MediaFile::factory()->create();

    $item = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
        'title' => 'Test Item',
    ]);

    $response = $this->actingAs($user)->put("/library/{$item->id}", [
        'title' => 'Updated Title',
        'description' => 'Updated description',
        'published_at' => '2025-03-10',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $item->refresh();
    expect($item->published_at)->not->toBeNull();
    expect($item->published_at->toDateString())->toBe('2025-03-10');
});

it('allows clearing published_at via edit endpoint', function () {
    $user = User::factory()->create();
    $mediaFile = MediaFile::factory()->create();

    $item = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
        'title' => 'Test Item',
        'published_at' => '2025-06-15 10:30:00',
    ]);

    $response = $this->actingAs($user)->put("/library/{$item->id}", [
        'title' => 'Updated Title',
        'description' => 'Updated description',
        'published_at' => '',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $item->refresh();
    expect($item->published_at)->toBeNull();
});

it('validates published_at must be a valid date', function () {
    $user = User::factory()->create();
    $mediaFile = MediaFile::factory()->create();

    $item = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
        'title' => 'Test Item',
    ]);

    $response = $this->actingAs($user)->put("/library/{$item->id}", [
        'title' => 'Updated Title',
        'description' => 'Updated description',
        'published_at' => 'not-a-date',
    ]);

    $response->assertSessionHasErrors('published_at');
});

it('rss feed uses published_at for pubDate when set', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $mediaFile = MediaFile::factory()->create([
        'file_path' => 'media/test-audio.mp3',
        'filesize' => 1234567,
        'mime_type' => 'audio/mpeg',
    ]);

    Storage::disk('public')->put('media/test-audio.mp3', 'fake audio content');

    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
        'title' => 'Test Episode',
        'description' => 'Test Description',
        'source_type' => 'upload',
        'published_at' => '2025-06-15 10:30:00',
    ]);

    $feed = Feed::factory()->create([
        'user_id' => $user->id,
        'title' => 'Test Feed',
        'description' => 'Test Feed Description',
        'slug' => 'test-feed',
        'user_guid' => 'test-guid',
        'is_public' => true,
    ]);

    FeedItem::factory()->create([
        'feed_id' => $feed->id,
        'library_item_id' => $libraryItem->id,
    ]);

    $response = $this->get("/rss/{$feed->user_guid}/{$feed->slug}");

    $content = $response->getContent();

    $expectedDate = $libraryItem->fresh()->published_at->toRfc822String();
    expect($content)->toContain("<pubDate>{$expectedDate}</pubDate>");
});

it('rss feed falls back to created_at for pubDate when published_at is null', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $mediaFile = MediaFile::factory()->create([
        'file_path' => 'media/test-audio.mp3',
        'filesize' => 1234567,
        'mime_type' => 'audio/mpeg',
    ]);

    Storage::disk('public')->put('media/test-audio.mp3', 'fake audio content');

    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
        'title' => 'Test Episode',
        'description' => 'Test Description',
        'source_type' => 'upload',
    ]);

    $feed = Feed::factory()->create([
        'user_id' => $user->id,
        'title' => 'Test Feed',
        'description' => 'Test Feed Description',
        'slug' => 'test-feed',
        'user_guid' => 'test-guid',
        'is_public' => true,
    ]);

    FeedItem::factory()->create([
        'feed_id' => $feed->id,
        'library_item_id' => $libraryItem->id,
    ]);

    $response = $this->get("/rss/{$feed->user_guid}/{$feed->slug}");

    $content = $response->getContent();

    $expectedDate = $libraryItem->fresh()->created_at->toRfc822String();
    expect($content)->toContain("<pubDate>{$expectedDate}</pubDate>");
});

it('sets published_at from youtube upload_date metadata during processing', function () {
    $user = User::factory()->create();
    $mediaFile = MediaFile::factory()->create();

    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
        'source_type' => 'youtube',
        'processing_status' => ProcessingStatusType::COMPLETED,
    ]);

    $metadata = [
        'title' => 'YouTube Video Title',
        'description' => 'YouTube Description',
        'upload_date' => '20250115',
    ];

    $fileProcessor = app(\App\Services\YouTube\YouTubeFileProcessor::class);
    $fileProcessor->updateLibraryItemWithMetadata($libraryItem, $metadata);

    $libraryItem->refresh();
    expect($libraryItem->published_at)->not->toBeNull();
    expect($libraryItem->published_at->toDateString())->toBe('2025-01-15');
});

it('does not overwrite user-set published_at with youtube metadata', function () {
    $user = User::factory()->create();
    $mediaFile = MediaFile::factory()->create();

    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
        'source_type' => 'youtube',
        'processing_status' => ProcessingStatusType::COMPLETED,
        'published_at' => '2025-06-01 12:00:00',
    ]);

    $metadata = [
        'title' => 'YouTube Video Title',
        'description' => 'YouTube Description',
        'upload_date' => '20250115',
    ];

    $fileProcessor = app(\App\Services\YouTube\YouTubeFileProcessor::class);
    $fileProcessor->updateLibraryItemWithMetadata($libraryItem, $metadata);

    $libraryItem->refresh();
    expect($libraryItem->published_at->toDateString())->toBe('2025-06-01');
});

it('prevents unauthorized users from updating published_at', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $mediaFile = MediaFile::factory()->create();

    $item = LibraryItem::factory()->create([
        'user_id' => $owner->id,
        'media_file_id' => $mediaFile->id,
        'title' => 'Test Item',
    ]);

    $response = $this->actingAs($otherUser)->put("/library/{$item->id}", [
        'title' => 'Hacked Title',
        'description' => 'Hacked description',
        'published_at' => '2025-03-10',
    ]);

    $response->assertForbidden();
});

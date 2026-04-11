<?php

use App\Models\Feed;
use App\Models\FeedItem;
use App\Models\LibraryItem;
use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('rss feed includes items with proper enclosure', function () {
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

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/xml');

    $content = $response->getContent();

    expect($content)->toContain('<title>Test Episode</title>');
    expect($content)->toContain('<description><![CDATA[Test Description]]></description>');
    expect($content)->toContain('/files/media/test-audio.mp3');
    expect($content)->toContain('audio/mpeg');
});

test('rss feed requires proper enclosure for podcast apps', function () {
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

    expect($content)->toContain('<enclosure');
    expect($content)->toContain('/files/media/test-audio.mp3');
    expect($content)->toContain('type="audio/mpeg"');
    expect($content)->toContain('length="1234567"');
});

test('rss feed includes youtube items with converted audio', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $mediaFile = MediaFile::factory()->create([
        'file_path' => 'media/youtube-audio.mp3',
        'filesize' => 5432100,
        'mime_type' => 'audio/mpeg',
    ]);

    Storage::disk('public')->put('media/youtube-audio.mp3', 'fake youtube audio content');

    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
        'title' => 'YouTube Video',
        'description' => 'YouTube Description',
        'source_type' => 'youtube',
        'source_url' => 'https://youtube.com/watch?v=test123',
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

    expect($content)->toContain('YouTube Video');
    expect($content)->toContain('<enclosure');
    expect($content)->toContain('/files/media/youtube-audio.mp3');
    expect($content)->toContain('type="audio/mpeg"');
    expect($content)->toContain('length="5432100"');
});

test('rss feed excludes youtube items without converted audio', function () {
    $user = User::factory()->create();

    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'title' => 'YouTube Video',
        'description' => 'YouTube Description',
        'source_type' => 'youtube',
        'source_url' => 'https://youtube.com/watch?v=test123',
        'media_file_id' => null,
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

    expect($content)->not->toContain('YouTube Video');
    expect($content)->not->toContain('<enclosure');
});

test('rss feed excludes items without media files', function () {
    $user = User::factory()->create();

    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'title' => 'Item Without Media',
        'description' => 'No media file',
        'source_type' => 'upload',
        'media_file_id' => null,
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

    expect($content)->not->toContain('Item Without Media');
    expect($content)->not->toContain('<enclosure');
});

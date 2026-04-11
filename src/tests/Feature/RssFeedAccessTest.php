<?php

use App\Models\Feed;
use App\Models\FeedItem;
use App\Models\LibraryItem;
use App\Models\MediaFile;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create([
        'email_verified_at' => now(),
        'approval_status' => 'approved',
    ]);
});

it('returns 404 for non-existent feed slug', function () {
    $response = $this->get('/rss/nonexistent-user-guid/nonexistent-slug');

    $response->assertNotFound();
});

it('generates RSS for public feed without token', function () {
    $feed = Feed::factory()->create([
        'user_id' => $this->user->id,
        'is_public' => true,
    ]);

    $mediaFile = MediaFile::factory()->create([
        'user_id' => $this->user->id,
        'mime_type' => 'audio/mpeg',
        'filesize' => 1024,
    ]);

    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $this->user->id,
        'media_file_id' => $mediaFile->id,
    ]);

    FeedItem::factory()->create([
        'feed_id' => $feed->id,
        'library_item_id' => $libraryItem->id,
        'sequence' => 0,
    ]);

    $response = $this->get("/rss/{$feed->user_guid}/{$feed->slug}");

    $response->assertSuccessful();
    $response->assertHeader('Content-Type', 'application/xml');
});

it('rejects private feed access without token', function () {
    $feed = Feed::factory()->create([
        'user_id' => $this->user->id,
        'is_public' => false,
        'token' => 'secret-token',
    ]);

    $response = $this->get("/rss/{$feed->user_guid}/{$feed->slug}");

    $response->assertNotFound();
});

it('grants private feed access with valid token', function () {
    $feed = Feed::factory()->create([
        'user_id' => $this->user->id,
        'is_public' => false,
        'token' => 'secret-token',
    ]);

    $mediaFile = MediaFile::factory()->create([
        'user_id' => $this->user->id,
        'mime_type' => 'audio/mpeg',
        'filesize' => 1024,
    ]);

    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $this->user->id,
        'media_file_id' => $mediaFile->id,
    ]);

    FeedItem::factory()->create([
        'feed_id' => $feed->id,
        'library_item_id' => $libraryItem->id,
        'sequence' => 0,
    ]);

    $response = $this->get("/rss/{$feed->user_guid}/{$feed->slug}?token=secret-token");

    $response->assertSuccessful();
});

it('generates empty RSS feed with no items', function () {
    $feed = Feed::factory()->create([
        'user_id' => $this->user->id,
        'is_public' => true,
    ]);

    $response = $this->get("/rss/{$feed->user_guid}/{$feed->slug}");

    $response->assertSuccessful();
    $response->assertDontSee('<item>');
});

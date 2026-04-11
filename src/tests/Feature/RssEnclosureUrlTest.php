<?php

use App\Models\Feed;
use App\Models\FeedItem;
use App\Models\LibraryItem;
use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('enclosure URL from RSS feed is accessible and returns media file', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $audioContent = str_repeat('fake audio data ', 500);
    $filePath = 'media/test-audio.mp3';
    Storage::disk('public')->put($filePath, $audioContent);

    $mediaFile = MediaFile::factory()->create([
        'user_id' => $user->id,
        'file_path' => $filePath,
        'filesize' => strlen($audioContent),
        'mime_type' => 'audio/mpeg',
    ]);

    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
        'title' => 'Test Episode',
        'source_type' => 'upload',
    ]);

    $feed = Feed::factory()->create([
        'user_id' => $user->id,
        'is_public' => true,
    ]);

    FeedItem::factory()->create([
        'feed_id' => $feed->id,
        'library_item_id' => $libraryItem->id,
    ]);

    $rssResponse = $this->get("/rss/{$feed->user_guid}/{$feed->slug}");
    $rssResponse->assertSuccessful();

    $rssXml = $rssResponse->getContent();
    preg_match('/<enclosure url="([^"]+)"/', $rssXml, $matches);
    expect($matches)->toHaveCount(2);

    $enclosureUrl = $matches[1];
    $parsedUrl = parse_url($enclosureUrl);
    $enclosurePath = $parsedUrl['path'];

    $fileResponse = $this->get($enclosurePath);
    $fileResponse->assertSuccessful();
    $fileResponse->assertHeader('Content-Type', 'audio/mpeg');
});

test('enclosure URL from RSS feed returns 404 when file missing from disk', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $mediaFile = MediaFile::factory()->create([
        'user_id' => $user->id,
        'file_path' => 'media/missing-file.mp3',
        'filesize' => 1000,
        'mime_type' => 'audio/mpeg',
    ]);

    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
        'title' => 'Missing File Episode',
        'source_type' => 'upload',
    ]);

    $feed = Feed::factory()->create([
        'user_id' => $user->id,
        'is_public' => true,
    ]);

    FeedItem::factory()->create([
        'feed_id' => $feed->id,
        'library_item_id' => $libraryItem->id,
    ]);

    $rssResponse = $this->get("/rss/{$feed->user_guid}/{$feed->slug}");
    $rssResponse->assertSuccessful();

    $rssXml = $rssResponse->getContent();
    preg_match('/<enclosure url="([^"]+)"/', $rssXml, $matches);
    expect($matches)->toHaveCount(2);

    $enclosureUrl = $matches[1];
    $parsedUrl = parse_url($enclosureUrl);
    $enclosurePath = $parsedUrl['path'];

    $fileResponse = $this->get($enclosurePath);
    $fileResponse->assertNotFound();
});

test('rss_url accessor generates URL matching the files.show route', function () {
    Storage::fake('public');

    $mediaFile = MediaFile::factory()->create([
        'file_path' => 'media/abc123.mp3',
    ]);

    $expectedUrl = route('files.show', ['file_path' => 'media/abc123.mp3']);
    $rssUrl = $mediaFile->rss_url;

    expect($rssUrl)->toBe($expectedUrl);

    $parsedUrl = parse_url($rssUrl);
    expect($parsedUrl['path'])->toBe('/files/media/abc123.mp3');
});

test('rss_url uses /files/ route not /storage/ symlink', function () {
    Storage::fake('public');

    $mediaFile = MediaFile::factory()->create([
        'file_path' => 'media/test-file.mp3',
    ]);

    $rssUrl = $mediaFile->rss_url;
    $publicUrl = $mediaFile->public_url;

    expect($rssUrl)->toContain('/files/media/test-file.mp3');
    expect($publicUrl)->toContain('/storage/media/test-file.mp3');
    expect($rssUrl)->not->toBe($publicUrl);
});

test('private feed enclosure URL requires feed_token to access', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $audioContent = 'private audio content';
    $filePath = 'media/private-audio.mp3';
    Storage::disk('public')->put($filePath, $audioContent);

    $mediaFile = MediaFile::factory()->create([
        'user_id' => $user->id,
        'file_path' => $filePath,
        'filesize' => strlen($audioContent),
        'mime_type' => 'audio/mpeg',
    ]);

    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
        'title' => 'Private Episode',
        'source_type' => 'upload',
    ]);

    $feed = Feed::factory()->create([
        'user_id' => $user->id,
        'is_public' => false,
        'token' => 'secret-feed-token-12345',
    ]);

    FeedItem::factory()->create([
        'feed_id' => $feed->id,
        'library_item_id' => $libraryItem->id,
    ]);

    $rssResponse = $this->get("/rss/{$feed->user_guid}/{$feed->slug}?token=secret-feed-token-12345");
    $rssResponse->assertSuccessful();

    $rssXml = $rssResponse->getContent();
    preg_match('/<enclosure url="([^"]+)"/', $rssXml, $matches);
    expect($matches)->toHaveCount(2);

    $enclosureUrl = $matches[1];
    expect($enclosureUrl)->toContain('feed_token=secret-feed-token-12345');

    $parsedUrl = parse_url($enclosureUrl);
    $path = $parsedUrl['path'];
    parse_str($parsedUrl['query'] ?? '', $query);

    $this->get($path)->assertForbidden();

    $this->get($path . '?feed_token=wrong-token')->assertForbidden();

    $this->get($path . '?feed_token=secret-feed-token-12345')->assertSuccessful();
});

test('multiple items in RSS feed all have accessible enclosure URLs', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $feed = Feed::factory()->create([
        'user_id' => $user->id,
        'is_public' => true,
    ]);

    $files = [
        ['path' => 'media/episode-1.mp3', 'content' => str_repeat('audio1', 100)],
        ['path' => 'media/episode-2.mp3', 'content' => str_repeat('audio2', 200)],
        ['path' => 'media/episode-3.mp3', 'content' => str_repeat('audio3', 300)],
    ];

    foreach ($files as $i => $fileData) {
        Storage::disk('public')->put($fileData['path'], $fileData['content']);

        $mediaFile = MediaFile::factory()->create([
            'user_id' => $user->id,
            'file_path' => $fileData['path'],
            'filesize' => strlen($fileData['content']),
            'mime_type' => 'audio/mpeg',
        ]);

        $libraryItem = LibraryItem::factory()->create([
            'user_id' => $user->id,
            'media_file_id' => $mediaFile->id,
            'title' => 'Episode '.($i + 1),
            'source_type' => 'upload',
        ]);

        FeedItem::factory()->create([
            'feed_id' => $feed->id,
            'library_item_id' => $libraryItem->id,
            'sequence' => $i,
        ]);
    }

    $rssResponse = $this->get("/rss/{$feed->user_guid}/{$feed->slug}");
    $rssResponse->assertSuccessful();

    $rssXml = $rssResponse->getContent();
    preg_match_all('/<enclosure url="([^"]+)"/', $rssXml, $matches);
    expect($matches[1])->toHaveCount(3);

    foreach ($matches[1] as $enclosureUrl) {
        $parsedUrl = parse_url($enclosureUrl);
        $fileResponse = $this->get($parsedUrl['path']);
        $fileResponse->assertSuccessful();
        $fileResponse->assertHeader('Content-Type', 'audio/mpeg');
    }
});

test('rss_url works with hash-based file paths from processing pipeline', function () {
    Storage::fake('public');

    $fileHash = hash('sha256', 'test content');
    $filePath = 'media/'.$fileHash.'.mp3';

    $mediaFile = MediaFile::factory()->create([
        'file_path' => $filePath,
    ]);

    $rssUrl = $mediaFile->rss_url;
    $parsedUrl = parse_url($rssUrl);

    expect($parsedUrl['path'])->toBe('/files/'.$filePath);

    $route = app('router')->getRoutes()->getByAction('App\Http\Controllers\MediaController@show');
    expect($route)->not->toBeNull();
});

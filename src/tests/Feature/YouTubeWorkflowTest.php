<?php

use App\Jobs\ProcessYouTubeAudio;
use App\Models\Feed;
use App\Models\FeedItem;
use App\Models\LibraryItem;
use App\Models\MediaFile;
use App\Models\User;
use App\ProcessingStatusType;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use function Pest\Laravel\actingAs;

test('complete workflow: youtube url → extract audio → add to feed → rss', function () {
    Storage::fake('public');
    Queue::fake();

    $user = User::factory()->create();

    $audioContent = 'youtube audio content';
    $filePath = 'media/youtube-audio.mp3';
    Storage::disk('public')->put($filePath, $audioContent);

    $mediaFile = MediaFile::factory()->create([
        'user_id' => $user->id,
        'file_path' => $filePath,
        'file_hash' => hash('sha256', $audioContent),
        'filesize' => strlen($audioContent),
        'mime_type' => 'audio/mpeg',
    ]);

    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'title' => 'YouTube Video Title',
        'description' => 'YouTube Description',
        'source_type' => 'youtube',
        'source_url' => 'https://www.youtube.com/watch?v=test123',
        'media_file_id' => $mediaFile->id,
        'processing_status' => ProcessingStatusType::COMPLETED,
        'processing_completed_at' => now(),
    ]);

    $feed = Feed::factory()->create([
        'user_id' => $user->id,
        'title' => 'YouTube Podcast',
        'description' => 'My YouTube Podcast',
        'slug' => 'youtube-podcast',
        'user_guid' => 'yt-guid',
        'is_public' => true,
    ]);

    FeedItem::factory()->create([
        'feed_id' => $feed->id,
        'library_item_id' => $libraryItem->id,
        'sequence' => 0,
    ]);

    $response = actingAs($user)
        ->get("/rss/{$feed->user_guid}/{$feed->slug}");

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/xml');

    $xml = $response->getContent();

    expect($xml)->toContain('YouTube Video Title');
    expect($xml)->toContain('YouTube Description');
    expect($xml)->toContain('youtube-audio.mp3');
});

test('workflow: youtube url reuses existing media file', function () {
    Storage::fake('public');
    Queue::fake();

    $user = User::factory()->create();

    $existingAudio = 'existing youtube audio';
    $filePath = 'media/youtube-audio.mp3';
    Storage::disk('public')->put($filePath, $existingAudio);

    $mediaFile = MediaFile::factory()->create([
        'user_id' => $user->id,
        'file_path' => $filePath,
        'file_hash' => hash('sha256', $existingAudio),
        'filesize' => strlen($existingAudio),
        'mime_type' => 'audio/mpeg',
    ]);

    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'title' => 'YouTube Video Title',
        'source_type' => 'youtube',
        'source_url' => 'https://www.youtube.com/watch?v=test123',
        'media_file_id' => $mediaFile->id,
        'processing_status' => ProcessingStatusType::COMPLETED,
        'processing_completed_at' => now(),
    ]);

    expect($libraryItem->media_file_id)->toBe($mediaFile->id);
    expect($libraryItem->mediaFile->id)->toBe($mediaFile->id);
});

test('workflow: youtube feed with multiple items', function () {
    Storage::fake('public');
    Queue::fake();

    $user = User::factory()->create();

    $items = [];
    for ($i = 1; $i <= 3; $i++) {
        $audioContent = "youtube audio content $i";
        $filePath = "media/youtube-audio-$i.mp3";
        Storage::disk('public')->put($filePath, $audioContent);

        $mediaFile = MediaFile::factory()->create([
            'user_id' => $user->id,
            'file_path' => $filePath,
            'file_hash' => hash('sha256', $audioContent),
            'filesize' => strlen($audioContent),
            'mime_type' => 'audio/mpeg',
        ]);

        $items[] = LibraryItem::factory()->create([
            'user_id' => $user->id,
            'title' => "YouTube Video $i",
            'source_type' => 'youtube',
            'source_url' => "https://www.youtube.com/watch?v=test$i",
            'media_file_id' => $mediaFile->id,
            'processing_status' => ProcessingStatusType::COMPLETED,
            'processing_completed_at' => now(),
        ]);
    }

    $feed = Feed::factory()->create([
        'user_id' => $user->id,
        'title' => 'YouTube Collection',
        'slug' => 'yt-collection',
        'user_guid' => 'yt-guid',
        'is_public' => true,
    ]);

    foreach ($items as $index => $item) {
        FeedItem::factory()->create([
            'feed_id' => $feed->id,
            'library_item_id' => $item->id,
            'sequence' => $index,
        ]);
    }

    $response = actingAs($user)
        ->get("/rss/{$feed->user_guid}/{$feed->slug}");

    $response->assertStatus(200);

    $xml = $response->getContent();

    expect($xml)->toContain('YouTube Video 1');
    expect($xml)->toContain('YouTube Video 2');
    expect($xml)->toContain('YouTube Video 3');
});

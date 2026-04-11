<?php

use App\Models\Feed;
use App\Models\FeedItem;
use App\Models\LibraryItem;
use App\Models\MediaFile;
use App\Models\User;
use App\ProcessingStatusType;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

test('complete workflow: upload file → process → add to feed → generate rss', function () {
    Storage::fake('public');
    Queue::fake();

    $user = User::factory()->create();

    $audioContent = str_repeat('fake audio content for testing', 100);
    $filePath = 'media/test-audio.mp3';
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
        'media_file_id' => $mediaFile->id,
        'title' => 'Test Episode',
        'description' => 'Test Description',
        'source_type' => 'upload',
        'processing_status' => ProcessingStatusType::COMPLETED,
        'processing_completed_at' => now(),
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
        'sequence' => 0,
    ]);

    $response = actingAs($user)
        ->get("/rss/{$feed->user_guid}/{$feed->slug}");

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/xml');

    $xml = $response->getContent();

    expect($xml)->toContain('<title>Test Episode</title>');
    expect($xml)->toContain('<description><![CDATA[Test Description]]></description>');
    expect($xml)->toContain('test-audio.mp3');
});

test('workflow: upload → add to multiple feeds', function () {
    Storage::fake('public');
    Queue::fake();

    $user = User::factory()->create();

    $audioContent = str_repeat('audio', 1000);
    $filePath = 'media/audio.mp3';
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
        'media_file_id' => $mediaFile->id,
        'title' => 'Test Episode',
        'source_type' => 'upload',
        'processing_status' => ProcessingStatusType::COMPLETED,
        'processing_completed_at' => now(),
    ]);

    $feed1 = Feed::factory()->create([
        'user_id' => $user->id,
        'title' => 'Feed 1',
        'slug' => 'feed-1',
        'user_guid' => 'guid-1',
        'is_public' => true,
    ]);

    $feed2 = Feed::factory()->create([
        'user_id' => $user->id,
        'title' => 'Feed 2',
        'slug' => 'feed-2',
        'user_guid' => 'guid-2',
        'is_public' => true,
    ]);

    FeedItem::factory()->create(['feed_id' => $feed1->id, 'library_item_id' => $libraryItem->id, 'sequence' => 0]);
    FeedItem::factory()->create(['feed_id' => $feed2->id, 'library_item_id' => $libraryItem->id, 'sequence' => 0]);

    $response1 = actingAs($user)->get("/rss/{$feed1->user_guid}/{$feed1->slug}");
    $response2 = actingAs($user)->get("/rss/{$feed2->user_guid}/{$feed2->slug}");

    expect($response1->getContent())->toContain('Test Episode');
    expect($response2->getContent())->toContain('Test Episode');
});

test('workflow: upload with duplicate detection marks item', function () {
    Storage::fake('public');
    Queue::fake();

    $user = User::factory()->create();

    $audioContent = 'audio content hash';
    $filePath = 'media/audio.mp3';
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
        'media_file_id' => $mediaFile->id,
        'title' => 'Duplicate Episode',
        'source_type' => 'upload',
        'processing_status' => ProcessingStatusType::COMPLETED,
        'is_duplicate' => true,
        'duplicate_detected_at' => now(),
    ]);

    expect($libraryItem->is_duplicate)->toBeTrue();
    expect($libraryItem->duplicate_detected_at)->not->toBeNull();
});

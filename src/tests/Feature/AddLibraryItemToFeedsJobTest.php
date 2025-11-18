<?php

use App\Jobs\AddLibraryItemToFeedsJob;
use App\Models\Feed;
use App\Models\FeedItem;
use App\Models\LibraryItem;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('can add library item to multiple feeds after processing', function () {
    $user = User::factory()->create();
    $feeds = Feed::factory()->count(3)->create(['user_id' => $user->id]);
    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'processing_status' => App\ProcessingStatusType::COMPLETED,
    ]);

    Queue::fake();

    // Dispatch the job
    AddLibraryItemToFeedsJob::dispatch($libraryItem, [$feeds[0]->id, $feeds[2]->id]);

    // Assert the job was pushed
    Queue::assertPushed(AddLibraryItemToFeedsJob::class, function ($job) use ($libraryItem, $feeds) {
        return $job->libraryItem->id === $libraryItem->id &&
               $job->feedIds === [$feeds[0]->id, $feeds[2]->id];
    });
});

it('creates feed items with correct sequence numbers', function () {
    $user = User::factory()->create();
    $feed = Feed::factory()->create(['user_id' => $user->id]);

    // Create existing items in the feed
    $existingItem1 = LibraryItem::factory()->create(['user_id' => $user->id]);
    $existingItem2 = LibraryItem::factory()->create(['user_id' => $user->id]);

    FeedItem::create([
        'feed_id' => $feed->id,
        'library_item_id' => $existingItem1->id,
        'sequence' => 1,
    ]);

    FeedItem::create([
        'feed_id' => $feed->id,
        'library_item_id' => $existingItem2->id,
        'sequence' => 2,
    ]);

    $newLibraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'processing_status' => App\ProcessingStatusType::COMPLETED,
    ]);

    // Dispatch the job synchronously for testing
    $job = new AddLibraryItemToFeedsJob($newLibraryItem, [$feed->id]);
    $job->handle();

    // Assert the new item was added with sequence 3
    $this->assertDatabaseHas('feed_items', [
        'feed_id' => $feed->id,
        'library_item_id' => $newLibraryItem->id,
        'sequence' => 3,
    ]);
});

it('only adds items to feeds owned by the same user', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $user1Feed = Feed::factory()->create(['user_id' => $user1->id]);
    $user2Feed = Feed::factory()->create(['user_id' => $user2->id]);

    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user1->id,
        'processing_status' => App\ProcessingStatusType::COMPLETED,
    ]);

    // Try to add to both feeds
    $job = new AddLibraryItemToFeedsJob($libraryItem, [$user1Feed->id, $user2Feed->id]);
    $job->handle();

    // Should only be added to user1's feed
    $this->assertDatabaseHas('feed_items', [
        'feed_id' => $user1Feed->id,
        'library_item_id' => $libraryItem->id,
    ]);

    $this->assertDatabaseMissing('feed_items', [
        'feed_id' => $user2Feed->id,
        'library_item_id' => $libraryItem->id,
    ]);
});

it('handles empty feed ids array gracefully', function () {
    $libraryItem = LibraryItem::factory()->create([
        'processing_status' => App\ProcessingStatusType::COMPLETED,
    ]);

    // Should not throw any errors
    $job = new AddLibraryItemToFeedsJob($libraryItem, []);
    $job->handle();

    // No feed items should be created
    $this->assertDatabaseCount('feed_items', 0);
});

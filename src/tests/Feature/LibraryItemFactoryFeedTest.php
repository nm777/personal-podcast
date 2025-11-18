<?php

use App\Models\Feed;
use App\Models\User;
use App\Services\SourceProcessors\LibraryItemFactory;
use Illuminate\Support\Facades\Queue;

it('dispatches feed job when creating library item with feed ids', function () {
    $user = User::factory()->create();
    $feeds = Feed::factory()->count(2)->create(['user_id' => $user->id]);

    Queue::fake();

    $factory = new LibraryItemFactory;
    $validated = [
        'title' => 'Test Item',
        'description' => 'Test Description',
        'feed_ids' => [$feeds[0]->id, $feeds[1]->id],
    ];

    $libraryItem = $factory->createFromValidated($validated, 'upload', null, $user->id);

    // Assert library item was created
    $this->assertDatabaseHas('library_items', [
        'id' => $libraryItem->id,
        'title' => 'Test Item',
        'description' => 'Test Description',
        'user_id' => $user->id,
        'source_type' => 'upload',
    ]);

    // Assert job was dispatched
    Queue::assertPushed(\App\Jobs\AddLibraryItemToFeedsJob::class, function ($job) use ($libraryItem, $feeds) {
        return $job->libraryItem->id === $libraryItem->id &&
               $job->feedIds === [$feeds[0]->id, $feeds[1]->id];
    });
});

it('does not dispatch feed job when no feed ids provided', function () {
    $user = User::factory()->create();

    Queue::fake();

    $factory = new LibraryItemFactory;
    $validated = [
        'title' => 'Test Item',
        'description' => 'Test Description',
    ];

    $libraryItem = $factory->createFromValidated($validated, 'upload', null, $user->id);

    // Assert library item was created
    $this->assertDatabaseHas('library_items', [
        'id' => $libraryItem->id,
        'title' => 'Test Item',
    ]);

    // Assert no job was dispatched
    Queue::assertNotPushed(\App\Jobs\AddLibraryItemToFeedsJob::class);
});

it('dispatches job synchronously for completed items', function () {
    $user = User::factory()->create();
    $feed = Feed::factory()->create(['user_id' => $user->id]);

    $factory = new LibraryItemFactory;
    $mediaFile = \App\Models\MediaFile::factory()->create(['user_id' => $user->id]);

    $validated = [
        'title' => 'Test Item',
        'description' => 'Test Description',
        'feed_ids' => [$feed->id],
    ];

    $libraryItem = $factory->createFromValidatedWithMediaFile($mediaFile, $validated, 'upload', null, $user->id);

    // Debug: check library item status
    $this->assertEquals(\App\ProcessingStatusType::COMPLETED, $libraryItem->processing_status);
    $this->assertTrue($libraryItem->hasCompleted());

    // Since this creates a completed item, job should be dispatched synchronously
    // Check that feed item was created immediately
    $this->assertDatabaseHas('feed_items', [
        'feed_id' => $feed->id,
        'library_item_id' => $libraryItem->id,
    ]);
});

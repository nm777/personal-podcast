<?php

use App\Models\LibraryItem;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

it('shows processing status for media items', function () {
    Storage::fake('public');
    Queue::fake();

    $user = User::factory()->create();

    // Create a library item with pending status
    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'title' => 'Test Processing Item',
        'processing_status' => \App\ProcessingStatusType::PROCESSING,
        'processing_started_at' => now(),
    ]);

    $response = $this->actingAs($user)->get('/library');

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->has('libraryItems', 1)
            ->where('libraryItems.0.processing_status', \App\ProcessingStatusType::PROCESSING->value)
    );
});

it('updates processing status when job completes', function () {
    Storage::fake('public');
    Queue::fake();

    $user = User::factory()->create();

    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'title' => 'Test URL Audio',
        'source_type' => 'url',
        'source_url' => 'https://example.com/test-audio.mp3',
        'processing_status' => \App\ProcessingStatusType::PENDING,
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

    expect($libraryItem->processing_status)->toBe(\App\ProcessingStatusType::COMPLETED);
    expect($libraryItem->processing_completed_at)->not->toBeNull();
    expect($libraryItem->media_file_id)->not->toBeNull();
});

it('updates processing status when job fails', function () {
    Storage::fake('public');
    Queue::fake();

    $user = User::factory()->create();

    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'title' => 'Test Failed Item',
        'source_type' => 'url',
        'source_url' => 'https://example.com/missing-file.mp3',
        'processing_status' => \App\ProcessingStatusType::PENDING,
    ]);

    // Mock failed HTTP response
    Http::fake([
        'https://example.com/missing-file.mp3' => Http::response('Not Found', 404),
    ]);

    $job = new \App\Jobs\ProcessMediaFile($libraryItem, 'https://example.com/missing-file.mp3', null);
    $job->handle();

    $libraryItem->refresh();

    expect($libraryItem->processing_status)->toBe(\App\ProcessingStatusType::FAILED);
    expect($libraryItem->processing_completed_at)->not->toBeNull();
    expect($libraryItem->processing_error)->toContain('Failed to download file');
});

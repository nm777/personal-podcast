<?php

use App\Jobs\ProcessYouTubeAudio;
use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

it('can add media file from YouTube URL', function () {
    Storage::fake('public');
    Queue::fake();

    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/library', [
        'title' => 'Test YouTube Video',
        'description' => 'Test Description from YouTube',
        'source_type' => 'youtube',
        'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ]);

    $response->assertRedirect('/library');
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('library_items', [
        'user_id' => $user->id,
        'title' => 'Test YouTube Video',
        'description' => 'Test Description from YouTube',
        'source_type' => 'youtube',
        'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ]);

    Queue::assertPushed(ProcessYouTubeAudio::class);
});

it('validates YouTube URL requirements', function () {
    $user = User::factory()->create();

    // Test missing URL
    $response = $this->actingAs($user)->post('/library', [
        'title' => 'Test YouTube Video',
        'source_type' => 'youtube',
    ]);

    $response->assertSessionHasErrors(['source_url']);

    // Test invalid YouTube URL format
    $response = $this->actingAs($user)->post('/library', [
        'title' => 'Test YouTube Video',
        'source_type' => 'youtube',
        'source_url' => 'https://example.com/not-youtube.mp3',
    ]);

    $response->assertSessionHasErrors(['source_url']);
});

it('reuses existing media file when same YouTube URL is provided', function () {
    Storage::fake('public');
    Queue::fake();

    $user = User::factory()->create();

    // Create an existing media file from YouTube URL
    $mediaFile = MediaFile::factory()->create([
        'user_id' => $user->id,
        'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ]);

    $response = $this->actingAs($user)->post('/library', [
        'title' => 'Duplicate YouTube Video',
        'source_type' => 'youtube',
        'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ]);

    $response->assertRedirect('/library');
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('library_items', [
        'user_id' => $user->id,
        'title' => 'Duplicate YouTube Video',
        'media_file_id' => $mediaFile->id,
        'source_type' => 'youtube',
        'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ]);

    Queue::assertNotPushed(ProcessYouTubeAudio::class);
});

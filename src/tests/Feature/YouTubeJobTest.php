<?php

use App\Jobs\ProcessYouTubeAudio;
use App\Models\LibraryItem;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

it('processes YouTube audio job with logging', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'source_type' => 'youtube',
        'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ]);

    // Capture logs
    Log::shouldReceive('info')->atLeast()->once();
    Log::shouldReceive('error')->atLeast()->once();

    $job = new ProcessYouTubeAudio($libraryItem, 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');

    // This should fail since we're using a fake storage and the command won't actually work
    // but we should see the logging output
    $job->handle();
});

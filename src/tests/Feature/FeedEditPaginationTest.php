<?php

use App\Models\Feed;
use App\Models\LibraryItem;
use App\Models\MediaFile;
use App\Models\User;

it('limits user library items on feed edit page', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'approval_status' => 'approved',
    ]);

    $feed = Feed::factory()->create(['user_id' => $user->id]);

    $mediaFile = MediaFile::factory()->create(['user_id' => $user->id]);
    for ($i = 0; $i < 120; $i++) {
        LibraryItem::factory()->create([
            'user_id' => $user->id,
            'media_file_id' => $mediaFile->id,
        ]);
    }

    $response = $this->actingAs($user)->get(route('feeds.edit', $feed));

    $response->assertSuccessful();

    $userLibraryItems = $response->inertiaProps('userLibraryItems');
    expect(count($userLibraryItems))->toBeLessThanOrEqual(100);
});

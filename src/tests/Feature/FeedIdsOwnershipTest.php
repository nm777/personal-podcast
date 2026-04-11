<?php

use App\Models\Feed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

describe('feed_ids ownership validation', function () {
    it('rejects feed_ids belonging to another user', function () {
        $user = User::factory()->create(['approval_status' => 'approved']);
        $otherUser = User::factory()->create(['approval_status' => 'approved']);
        $otherFeed = Feed::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->post('/library', [
            'title' => 'Test item',
            'source_type' => 'upload',
            'file' => UploadedFile::fake()->create('test.mp3', 100),
            'feed_ids' => [$otherFeed->id],
        ]);

        $response->assertSessionHasErrors(['feed_ids.0']);
    });

    it('accepts feed_ids belonging to the authenticated user', function () {
        $user = User::factory()->create(['approval_status' => 'approved']);
        $feed = Feed::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post('/library', [
            'title' => 'Test item',
            'source_type' => 'upload',
            'file' => UploadedFile::fake()->create('test.mp3', 100),
            'feed_ids' => [$feed->id],
        ]);

        $response->assertSessionDoesntHaveErrors('feed_ids');
    });

    it('rejects a mix of owned and unowned feed_ids', function () {
        $user = User::factory()->create(['approval_status' => 'approved']);
        $otherUser = User::factory()->create(['approval_status' => 'approved']);
        $ownFeed = Feed::factory()->create(['user_id' => $user->id]);
        $otherFeed = Feed::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->post('/library', [
            'title' => 'Test item',
            'source_type' => 'upload',
            'file' => UploadedFile::fake()->create('test.mp3', 100),
            'feed_ids' => [$ownFeed->id, $otherFeed->id],
        ]);

        $response->assertSessionHasErrors(['feed_ids.1']);
    });
});

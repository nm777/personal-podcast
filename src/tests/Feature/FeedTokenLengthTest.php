<?php

use App\Models\Feed;
use App\Models\User;

it('creates a feed with a 64-character token', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'approval_status' => 'approved',
    ]);

    $response = $this->actingAs($user)->post(route('feeds.store'), [
        'title' => 'Test Feed',
        'description' => 'A test feed',
        'is_public' => false,
    ]);

    $response->assertRedirect();

    $feed = Feed::where('user_id', $user->id)->first();
    expect($feed)->not->toBeNull();
    expect(strlen($feed->token))->toBe(64);
});

it('creates unique tokens for different feeds', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'approval_status' => 'approved',
    ]);

    $this->actingAs($user)->post(route('feeds.store'), [
        'title' => 'Feed One',
        'is_public' => false,
    ]);

    $this->actingAs($user)->post(route('feeds.store'), [
        'title' => 'Feed Two',
        'is_public' => false,
    ]);

    $feeds = Feed::where('user_id', $user->id)->get();
    expect($feeds)->toHaveCount(2);
    expect($feeds[0]->token)->not->toBe($feeds[1]->token);
});

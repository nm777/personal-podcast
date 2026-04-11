<?php

use App\Models\User;

it('prevents unauthenticated access to URL duplicate check', function () {
    $response = $this->postJson('/check-url-duplicate', [
        'url' => 'https://example.com/audio.mp3',
    ]);

    $response->assertUnauthorized();
});

it('prevents pending user from accessing dashboard', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'approval_status' => 'pending',
    ]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertRedirect(route('login'));
});

it('prevents rejected user from accessing dashboard', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'approval_status' => 'rejected',
        'rejection_reason' => 'Not a good fit',
    ]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertRedirect(route('login'));
});

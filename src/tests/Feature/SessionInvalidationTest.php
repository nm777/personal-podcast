<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Session invalidation on forced logout', function () {
    it('invalidates and regenerates session for unapproved user', function () {
        $user = User::factory()->create(['approval_status' => 'pending']);

        $response = $this->actingAs($user)
            ->withSession(['test-key' => 'should-be-cleared'])
            ->get('/dashboard');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
        expect(session('test-key'))->toBeNull();
    });

    it('invalidates and regenerates session for rejected user', function () {
        $user = User::factory()->create(['approval_status' => 'rejected']);

        $response = $this->actingAs($user)
            ->withSession(['test-key' => 'should-be-cleared'])
            ->get('/dashboard');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
        expect(session('test-key'))->toBeNull();
    });
});

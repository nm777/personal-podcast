<?php

use App\Models\LibraryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

describe('published_at date field', function () {
    it('stores published_at on create', function () {
        $user = User::factory()->create(['approval_status' => 'approved']);

        $response = $this->actingAs($user)->post('/library', [
            'title' => 'Test item',
            'source_type' => 'upload',
            'file' => UploadedFile::fake()->create('test.mp3', 100),
            'published_at' => '2026-01-15',
        ]);

        $response->assertRedirect();

        $item = LibraryItem::where('user_id', $user->id)->first();
        expect($item->published_at)->not->toBeNull();
        expect($item->published_at->format('Y-m-d'))->toBe('2026-01-15');
    });

    it('updates published_at on edit', function () {
        $user = User::factory()->create(['approval_status' => 'approved']);
        $item = LibraryItem::factory()->create([
            'user_id' => $user->id,
            'published_at' => null,
        ]);

        $response = $this->actingAs($user)->put("/library/{$item->id}", [
            'title' => $item->title,
            'description' => $item->description,
            'published_at' => '2026-03-20',
        ]);

        $response->assertRedirect();
        expect($item->fresh()->published_at->format('Y-m-d'))->toBe('2026-03-20');
    });

    it('accepts null published_at', function () {
        $user = User::factory()->create(['approval_status' => 'approved']);
        $item = LibraryItem::factory()->create([
            'user_id' => $user->id,
            'published_at' => '2026-01-01',
        ]);

        $response = $this->actingAs($user)->put("/library/{$item->id}", [
            'title' => $item->title,
            'description' => $item->description,
            'published_at' => '',
        ]);

        $response->assertRedirect();
        expect($item->fresh()->published_at)->toBeNull();
    });
});

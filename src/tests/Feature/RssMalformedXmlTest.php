<?php

use App\Models\Feed;
use App\Models\LibraryItem;
use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

describe('RssController malformed XML handling', function () {
    it('does not cache malformed RSS XML', function () {
        $user = User::factory()->create();
        $feed = Feed::factory()->create([
            'user_id' => $user->id,
            'is_public' => true,
        ]);

        $mediaFile = MediaFile::factory()->create(['user_id' => $user->id]);
        $item = LibraryItem::factory()->create([
            'user_id' => $user->id,
            'title' => "Title with \x00 null byte",
            'media_file_id' => $mediaFile->id,
            'processing_status' => 'completed',
        ]);
        $feed->items()->create([
            'library_item_id' => $item->id,
            'sequence' => 0,
        ]);

        $cacheKey = "rss.{$feed->id}";
        Cache::forget($cacheKey);
        expect(Cache::has($cacheKey))->toBeFalse();

        $response = $this->get("/rss/{$feed->user_guid}/{$feed->slug}");

        $response->assertStatus(500);
        expect(Cache::has($cacheKey))->toBeFalse();
    });

    it('caches valid RSS XML', function () {
        $user = User::factory()->create();
        $feed = Feed::factory()->create([
            'user_id' => $user->id,
            'is_public' => true,
        ]);

        $mediaFile = MediaFile::factory()->create(['user_id' => $user->id]);
        $item = LibraryItem::factory()->create([
            'user_id' => $user->id,
            'title' => 'Valid title',
            'media_file_id' => $mediaFile->id,
            'processing_status' => 'completed',
        ]);
        $feed->items()->create([
            'library_item_id' => $item->id,
            'sequence' => 0,
        ]);

        $cacheKey = "rss.{$feed->id}";
        Cache::forget($cacheKey);

        $response = $this->get("/rss/{$feed->user_guid}/{$feed->slug}");

        $response->assertStatus(200);
        expect(Cache::has($cacheKey))->toBeTrue();
    });
});

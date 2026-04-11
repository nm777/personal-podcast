<?php

use App\Models\Feed;
use App\Models\LibraryItem;
use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('RSS CDATA handling', function () {
    it('wraps descriptions in CDATA for special characters', function () {
        $user = User::factory()->create();
        $feed = Feed::factory()->create([
            'user_id' => $user->id,
            'is_public' => true,
            'description' => 'Feed with <special> & "chars"',
        ]);
        $mediaFile = MediaFile::factory()->create(['user_id' => $user->id]);
        $item = LibraryItem::factory()->create([
            'user_id' => $user->id,
            'title' => 'Item with <tag> & stuff',
            'description' => 'Desc with <html> & "quotes"',
            'media_file_id' => $mediaFile->id,
            'processing_status' => 'completed',
        ]);
        $feed->items()->create([
            'library_item_id' => $item->id,
            'sequence' => 0,
        ]);

        $response = $this->get("/rss/{$feed->user_guid}/{$feed->slug}");
        $response->assertStatus(200);

        $xml = $response->getContent();
        expect($xml)->toContain('<![CDATA[');
        expect($xml)->toContain('Desc with <html> & "quotes"');
        expect($xml)->toContain('Feed with <special> & "chars"');
    });
});

<?php

namespace App\Jobs;

use App\Models\Feed;
use App\Models\FeedItem;
use App\Models\LibraryItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AddLibraryItemToFeedsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public LibraryItem $libraryItem,
        public array $feedIds
    ) {}

    public function handle(): void
    {
        $feeds = Feed::whereIn('id', $this->feedIds)
            ->where('user_id', $this->libraryItem->user_id)
            ->get();

        foreach ($feeds as $feed) {
            // Get the next sequence number for this feed
            $maxSequence = $feed->items()->max('sequence') ?? 0;

            FeedItem::create([
                'feed_id' => $feed->id,
                'library_item_id' => $this->libraryItem->id,
                'sequence' => $maxSequence + 1,
            ]);
        }
    }
}

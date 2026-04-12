<?php

namespace App\Jobs;

use App\Models\LibraryItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CleanupDuplicateLibraryItem implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public LibraryItem $libraryItem) {}

    public function handle(): void
    {
        $libraryItem = $this->libraryItem->fresh();

        if (! $libraryItem || ! $libraryItem->is_duplicate) {
            return;
        }

        $libraryItem->delete();
    }
}

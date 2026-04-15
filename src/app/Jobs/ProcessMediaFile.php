<?php

namespace App\Jobs;

use App\Enums\ProcessingStatusType;
use App\Models\LibraryItem;
use App\Services\MediaProcessing\MediaProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessMediaFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private LibraryItem $libraryItem,
        private ?string $sourceUrl = null,
        private ?string $filePath = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(MediaProcessingService $mediaProcessing): void
    {

        if ($this->sourceUrl) {
            $mediaProcessing->processFromUrl($this->libraryItem, $this->sourceUrl);
        } elseif ($this->filePath) {
            $mediaProcessing->processFromFile($this->libraryItem, $this->filePath, $this->sourceUrl);
        } else {
            $this->libraryItem->update([
                'processing_status' => ProcessingStatusType::FAILED,
                'processing_completed_at' => now(),
                'processing_error' => 'No source URL or file path provided',
            ]);
        }
    }
}

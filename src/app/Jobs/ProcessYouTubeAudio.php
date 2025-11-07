<?php

namespace App\Jobs;

use App\Models\LibraryItem;
use App\Models\MediaFile;
use App\Services\YouTubeUrlValidator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class ProcessYouTubeAudio implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $libraryItem;

    protected $youtubeUrl;

    /**
     * Create a new job instance.
     */
    public function __construct(LibraryItem $libraryItem, string $youtubeUrl)
    {
        $this->libraryItem = $libraryItem;
        $this->youtubeUrl = $youtubeUrl;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Mark as processing
        $this->libraryItem->update([
            'processing_status' => 'processing',
            'processing_started_at' => now(),
            'processing_error' => null,
        ]);

        // Check if we already have this file from same URL
        $existingMediaFile = MediaFile::findBySourceUrl($this->youtubeUrl);

        if ($existingMediaFile) {
            // File already exists from this URL, just link it
            $this->libraryItem->media_file_id = $existingMediaFile->id;
            $this->libraryItem->update([
                'processing_status' => 'completed',
                'processing_completed_at' => now(),
            ]);

            return;
        }

        $videoId = YouTubeUrlValidator::extractVideoId($this->youtubeUrl);

        if (! $videoId) {
            $this->libraryItem->delete();

            return;
        }

        $tempDir = 'temp-youtube/'.uniqid();
        $tempPath = $tempDir.'/audio.%(ext)s';
        $finalTempPath = $tempDir.'/audio.mp3';

        try {
            // Create temp directory
            Storage::disk('local')->makeDirectory($tempDir);

            // Download audio using yt-dlp
            $process = new Process([
                'yt-dlp',
                '--extract-audio',
                '--audio-format', 'mp3',
                '--audio-quality', '0', // best quality
                '--no-playlist',
                '--output', Storage::disk('local')->path($tempPath),
                $this->youtubeUrl,
            ]);

            $process->setTimeout(300); // 5 minutes timeout
            $process->run();

            if (! $process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            // Find the downloaded file (yt-dlp might create different extensions)
            $files = Storage::disk('local')->allFiles($tempDir);
            $downloadedFile = null;

            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_FILENAME) === 'audio') {
                    $downloadedFile = $file;
                    break;
                }
            }

            if (! $downloadedFile || ! Storage::disk('local')->exists($downloadedFile)) {
                $this->libraryItem->delete();

                return;
            }

            // Get file info
            $fullPath = Storage::disk('local')->path($downloadedFile);
            $fileHash = hash_file('sha256', $fullPath);
            $extension = pathinfo($fullPath, PATHINFO_EXTENSION);
            $finalPath = 'media/'.$fileHash.'.'.$extension;

            // Check if file already exists with this hash
            $mediaFile = MediaFile::where('file_hash', $fileHash)->first();

            if (! $mediaFile) {
                // Move file to final location using hash
                Storage::disk('local')->move($downloadedFile, $finalPath);

                // Get video metadata for title/description if needed
                $metadataProcess = new Process([
                    'yt-dlp',
                    '--dump-json',
                    '--no-playlist',
                    $this->youtubeUrl,
                ]);

                $metadataProcess->run();
                $metadata = null;

                if ($metadataProcess->isSuccessful()) {
                    $metadata = json_decode($metadataProcess->getOutput(), true);
                }

                $mediaFile = MediaFile::create([
                    'file_path' => $finalPath,
                    'file_hash' => $fileHash,
                    'mime_type' => File::mimeType(Storage::disk('local')->path($finalPath)),
                    'filesize' => File::size(Storage::disk('local')->path($finalPath)),
                    'source_url' => $this->youtubeUrl,
                ]);

                // Update library item with YouTube metadata if available
                if ($metadata && ! $this->libraryItem->title) {
                    $this->libraryItem->title = $metadata['title'] ?? $this->libraryItem->title;
                    $this->libraryItem->description = $metadata['description'] ?? $this->libraryItem->description;
                }

                $this->libraryItem->media_file_id = $mediaFile->id;
                $this->libraryItem->update([
                    'processing_status' => 'completed',
                    'processing_completed_at' => now(),
                ]);
            } else {
                // File already exists, clean up temp file
                Storage::disk('local')->delete($downloadedFile);

                // Update source URL if this is first time we've seen it from this URL
                if (! $mediaFile->source_url) {
                    $mediaFile->source_url = $this->youtubeUrl;
                    $mediaFile->save();
                }

                // Mark this library item as a duplicate
                $this->libraryItem->media_file_id = $mediaFile->id;
                $this->libraryItem->is_duplicate = true;
                $this->libraryItem->duplicate_detected_at = now();
                $this->libraryItem->update([
                    'processing_status' => 'completed',
                    'processing_completed_at' => now(),
                ]);

                // Schedule cleanup of this duplicate entry
                CleanupDuplicateLibraryItem::dispatch($this->libraryItem)->delay(now()->addMinutes(5));

                // Store flash message for user notification
                session()->flash('warning', 'Duplicate file detected. This file already exists in your library and will be removed automatically in 5 minutes.');
            }

        } catch (\Exception $e) {
            $this->libraryItem->update([
                'processing_status' => 'failed',
                'processing_completed_at' => now(),
                'processing_error' => 'YouTube processing failed: '.$e->getMessage(),
            ]);
        } finally {
            // Clean up temp directory
            if (Storage::disk('local')->exists($tempDir)) {
                Storage::disk('local')->deleteDirectory($tempDir);
            }
        }
    }
}

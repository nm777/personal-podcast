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
use Illuminate\Support\Facades\Log;
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
        Log::info('ProcessYouTubeAudio job started', [
            'library_item_id' => $this->libraryItem->id,
            'youtube_url' => $this->youtubeUrl,
        ]);

        // Mark as processing
        $this->libraryItem->update([
            'processing_status' => 'processing',
            'processing_started_at' => now(),
            'processing_error' => null,
        ]);

        // Check if we already have this file from same URL
        $existingMediaFile = MediaFile::findBySourceUrl($this->youtubeUrl);

        if ($existingMediaFile) {
            Log::info('Found existing media file for URL', [
                'library_item_id' => $this->libraryItem->id,
                'existing_media_file_id' => $existingMediaFile->id,
                'youtube_url' => $this->youtubeUrl,
            ]);

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
            Log::error('Failed to extract video ID from URL', [
                'library_item_id' => $this->libraryItem->id,
                'youtube_url' => $this->youtubeUrl,
            ]);
            $this->libraryItem->delete();

            return;
        }

        Log::info('Extracted video ID', [
            'library_item_id' => $this->libraryItem->id,
            'video_id' => $videoId,
            'youtube_url' => $this->youtubeUrl,
        ]);

        $tempDir = 'temp-youtube/'.uniqid();
        $tempPath = $tempDir.'/audio.%(ext)s';

        Log::info('Setting up download', [
            'library_item_id' => $this->libraryItem->id,
            'temp_dir' => $tempDir,
            'temp_path' => $tempPath,
        ]);

        try {
            // Create temp directory
            Storage::disk('public')->makeDirectory($tempDir);
            Log::info('Created temp directory', [
                'library_item_id' => $this->libraryItem->id,
                'temp_dir' => $tempDir,
            ]);

            // Download audio using yt-dlp
            $command = [
                'yt-dlp',
                '--extract-audio',
                '--audio-format',
                'mp3',
                '--audio-quality',
                '0', // best quality
                '--no-playlist',
                '--output',
                Storage::disk('public')->path($tempPath),
                $this->youtubeUrl,
            ];

            Log::info('Running yt-dlp command', [
                'library_item_id' => $this->libraryItem->id,
                'command' => implode(' ', $command),
            ]);

            $process = new Process($command);
            $process->setTimeout(300); // 5 minutes timeout
            $process->run();

            Log::info('yt-dlp command completed', [
                'library_item_id' => $this->libraryItem->id,
                'is_successful' => $process->isSuccessful(),
                'exit_code' => $process->getExitCode(),
                'output' => $process->getOutput(),
                'error_output' => $process->getErrorOutput(),
            ]);

            if (! $process->isSuccessful()) {
                Log::error('yt-dlp command failed', [
                    'library_item_id' => $this->libraryItem->id,
                    'exit_code' => $process->getExitCode(),
                    'output' => $process->getOutput(),
                    'error_output' => $process->getErrorOutput(),
                ]);
                throw new ProcessFailedException($process);
            }

            // Find the downloaded file (yt-dlp might create different extensions)
            $files = Storage::disk('public')->allFiles($tempDir);
            $downloadedFile = null;

            Log::info('Looking for downloaded files', [
                'library_item_id' => $this->libraryItem->id,
                'temp_dir' => $tempDir,
                'files_found' => $files,
            ]);

            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_FILENAME) === 'audio') {
                    $downloadedFile = $file;
                    break;
                }
            }

            if (! $downloadedFile || ! Storage::disk('public')->exists($downloadedFile)) {
                Log::error('No downloaded file found', [
                    'library_item_id' => $this->libraryItem->id,
                    'temp_dir' => $tempDir,
                    'files_found' => $files,
                    'downloaded_file' => $downloadedFile,
                    'file_exists' => $downloadedFile ? Storage::disk('public')->exists($downloadedFile) : false,
                ]);
                $this->libraryItem->delete();

                return;
            }

            Log::info('Found downloaded file', [
                'library_item_id' => $this->libraryItem->id,
                'downloaded_file' => $downloadedFile,
                'file_size' => Storage::disk('public')->size($downloadedFile),
            ]);

            // Get file info
            $fullPath = Storage::disk('public')->path($downloadedFile);
            $fileHash = hash_file('sha256', $fullPath);
            $extension = pathinfo($fullPath, PATHINFO_EXTENSION);
            $finalPath = 'media/'.$fileHash.'.'.$extension;

            Log::info('Calculated file info', [
                'library_item_id' => $this->libraryItem->id,
                'full_path' => $fullPath,
                'file_hash' => $fileHash,
                'extension' => $extension,
                'final_path' => $finalPath,
            ]);

            // Check if file already exists with this hash
            $mediaFile = MediaFile::where('file_hash', $fileHash)->first();

            if (! $mediaFile) {
                Log::info('Moving file to final location', [
                    'library_item_id' => $this->libraryItem->id,
                    'from' => $downloadedFile,
                    'to' => $finalPath,
                    'from_exists' => Storage::disk('public')->exists($downloadedFile),
                ]);

                // Move file to final location using hash
                $moveSuccess = Storage::disk('public')->move($downloadedFile, $finalPath);

                Log::info('File move completed', [
                    'library_item_id' => $this->libraryItem->id,
                    'move_success' => $moveSuccess,
                    'to_exists' => Storage::disk('public')->exists($finalPath),
                    'final_path' => $finalPath,
                ]);

                // Get video metadata for title/description if needed
                $metadataCommand = [
                    'yt-dlp',
                    '--dump-json',
                    '--no-playlist',
                    $this->youtubeUrl,
                ];

                Log::info('Getting video metadata', [
                    'library_item_id' => $this->libraryItem->id,
                    'command' => implode(' ', $metadataCommand),
                ]);

                $metadataProcess = new Process($metadataCommand);
                $metadataProcess->run();
                $metadata = null;

                Log::info('Metadata command completed', [
                    'library_item_id' => $this->libraryItem->id,
                    'is_successful' => $metadataProcess->isSuccessful(),
                    'output' => $metadataProcess->getOutput(),
                    'error_output' => $metadataProcess->getErrorOutput(),
                ]);

                if ($metadataProcess->isSuccessful()) {
                    $metadata = json_decode($metadataProcess->getOutput(), true);
                    Log::info('Parsed metadata', [
                        'library_item_id' => $this->libraryItem->id,
                        'title' => $metadata['title'] ?? 'N/A',
                        'description' => isset($metadata['description']) ? substr($metadata['description'], 0, 100).'...' : 'N/A',
                    ]);
                }

                $mimeType = File::mimeType(Storage::disk('public')->path($finalPath));
                $fileSize = File::size(Storage::disk('public')->path($finalPath));

                Log::info('Creating media file record', [
                    'library_item_id' => $this->libraryItem->id,
                    'file_path' => $finalPath,
                    'file_hash' => $fileHash,
                    'mime_type' => $mimeType,
                    'filesize' => $fileSize,
                    'source_url' => $this->youtubeUrl,
                ]);

                $mediaFile = MediaFile::create([
                    'file_path' => $finalPath,
                    'file_hash' => $fileHash,
                    'mime_type' => $mimeType,
                    'filesize' => $fileSize,
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
                Storage::disk('public')->delete($downloadedFile);

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
            Log::error('ProcessYouTubeAudio job failed', [
                'library_item_id' => $this->libraryItem->id,
                'youtube_url' => $this->youtubeUrl,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            $this->libraryItem->update([
                'processing_status' => 'failed',
                'processing_completed_at' => now(),
                'processing_error' => 'YouTube processing failed: '.$e->getMessage(),
            ]);
        } finally {
            // Clean up temp directory
            if (Storage::disk('public')->exists($tempDir)) {
                Storage::disk('public')->deleteDirectory($tempDir);
            }
        }
    }
}

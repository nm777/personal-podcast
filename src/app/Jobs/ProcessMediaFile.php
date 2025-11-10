<?php

namespace App\Jobs;

use App\Models\LibraryItem;
use App\Models\MediaFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ProcessMediaFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $libraryItem;

    protected $sourceUrl;

    protected $filePath;

    /**
     * Create a new job instance.
     */
    public function __construct(LibraryItem $libraryItem, $sourceUrl = null, $filePath = null)
    {
        $this->libraryItem = $libraryItem;
        $this->sourceUrl = $sourceUrl;
        $this->filePath = $filePath;
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

        $tempPath = null;
        $mediaFile = null;

        try {
            // Check if we already have this file from the same URL
            if ($this->sourceUrl) {
                $mediaFile = MediaFile::findBySourceUrl($this->sourceUrl);

                if ($mediaFile) {
                    // File already exists from this URL, just link it
                    $this->libraryItem->media_file_id = $mediaFile->id;
                    $this->libraryItem->update([
                        'processing_status' => 'completed',
                        'processing_completed_at' => now(),
                    ]);

                    return;
                }
            }

            if ($this->sourceUrl) {
                try {
                    $response = Http::timeout(60)->withOptions([
                        'allow_redirects' => [
                            'max' => 5,
                            'strict' => true,
                            'referer' => true,
                            'protocols' => ['http', 'https'],
                            'track_redirects' => true,
                        ],
                    ])->get($this->sourceUrl);

                    if (! $response->successful()) {
                        $this->libraryItem->update([
                            'processing_status' => 'failed',
                            'processing_completed_at' => now(),
                            'processing_error' => 'Failed to download file: HTTP '.$response->status(),
                        ]);

                        return;
                    }

                    $contents = $response->body();

                    if (empty($contents)) {
                        $this->libraryItem->update([
                            'processing_status' => 'failed',
                            'processing_completed_at' => now(),
                            'processing_error' => 'Downloaded file is empty',
                        ]);

                        return;
                    }

                    // Check if we got HTML instead of expected media content
                    if (str_starts_with($contents, '<!DOCTYPE html') || str_starts_with($contents, '<html')) {
                        // Try to extract JavaScript redirect URL (handle both patterns)
                        $redirectUrl = null;

                        // Pattern 1: window.location.replace('url')
                        if (preg_match('/window\.location\.replace\([\'"]([^\'"]+)[\'"]\)/', $contents, $matches)) {
                            $redirectUrl = $matches[1];
                        }
                        // Pattern 2: window.location.href.replace('pattern', 'replacement')
                        elseif (preg_match('/window\.location\.href\.replace\([\'"]([^\'"]+)[\'"],\s*[\'"]([^\'"]+)[\'"]\)/', $contents, $matches)) {
                            $pattern = $matches[1];
                            $replacement = $matches[2];
                            // Apply the replacement pattern to the original URL
                            $redirectUrl = str_replace($pattern, $replacement, $this->sourceUrl);
                        }

                        if ($redirectUrl) {
                            // Make it absolute if relative
                            if (! str_starts_with($redirectUrl, 'http')) {
                                $parsedUrl = parse_url($this->sourceUrl);
                                $baseUrl = $parsedUrl['scheme'].'://'.$parsedUrl['host'];
                                if (str_starts_with($redirectUrl, '/')) {
                                    $redirectUrl = $baseUrl.$redirectUrl;
                                } else {
                                    // Handle relative paths
                                    $path = dirname($parsedUrl['path']);
                                    $redirectUrl = $baseUrl.$path.'/'.$redirectUrl;
                                }
                            }

                            // Try the redirected URL
                            $redirectResponse = Http::timeout(60)->get($redirectUrl);
                            if ($redirectResponse->successful() && ! str_starts_with($redirectResponse->body(), '<!DOCTYPE html')) {
                                $contents = $redirectResponse->body();
                            } else {
                                $this->libraryItem->update([
                                    'processing_status' => 'failed',
                                    'processing_completed_at' => now(),
                                    'processing_error' => 'Download failed: Got HTML redirect page instead of media file',
                                ]);

                                return;
                            }
                        } else {
                            $this->libraryItem->update([
                                'processing_status' => 'failed',
                                'processing_completed_at' => now(),
                                'processing_error' => 'Download failed: Got HTML content instead of media file',
                            ]);

                            return;
                        }
                    }

                    // Validate that we have actual media content by checking file signature
                    $validMediaSignatures = [
                        'RIFF' => true, // WAV/AVI
                        'OggS' => true, // OGG
                        'fLaC' => true, // FLAC
                        'MP4' => true,  // M4A/MP4
                        "\xFF\xFB" => true, // MP3
                        "\xFF\xF3" => true, // MP3
                        "\xFF\xF2" => true, // MP3
                    ];

                    $fileSignature = substr($contents, 0, 4);
                    $isValidMedia = isset($validMediaSignatures[$fileSignature]) ||
                                   isset($validMediaSignatures[substr($contents, 0, 2)]) ||
                                   str_starts_with($fileSignature, 'ID3'); // MP3 with ID3 tag

                    if (! $isValidMedia && strlen($contents) > 100) {
                        // Check if it might be a different audio format or corrupted
                        $this->libraryItem->update([
                            'processing_status' => 'failed',
                            'processing_completed_at' => now(),
                            'processing_error' => 'Download failed: Content does not appear to be a valid audio file',
                        ]);

                        return;
                    }

                    $tempPath = 'temp-uploads/'.uniqid().'_'.basename(parse_url($this->sourceUrl, PHP_URL_PATH) ?: 'download');
                    Storage::disk('public')->put($tempPath, $contents);
                } catch (\Exception $e) {
                    $this->libraryItem->update([
                        'processing_status' => 'failed',
                        'processing_completed_at' => now(),
                        'processing_error' => 'Download failed: '.$e->getMessage(),
                    ]);

                    return;
                }
            } elseif ($this->filePath) {
                $tempPath = $this->filePath;
            }

            if (! $tempPath || ! Storage::disk('public')->exists($tempPath)) {
                $this->libraryItem->update([
                    'processing_status' => 'failed',
                    'processing_completed_at' => now(),
                    'processing_error' => 'Temp file not found or inaccessible',
                ]);

                return;
            }

            $fullPath = Storage::disk('public')->path($tempPath);
            $fileHash = hash_file('sha256', $fullPath);
            $extension = pathinfo($fullPath, PATHINFO_EXTENSION);
            $finalPath = 'media/'.$fileHash.'.'.$extension;

            // Check if file already exists with this hash (but different source)
            $mediaFile = MediaFile::where('file_hash', $fileHash)->first();

            if (! $mediaFile) {
                // Move file to final location using hash
                Storage::disk('public')->move($tempPath, $finalPath);

                $mediaFile = MediaFile::create([
                    'file_path' => $finalPath,
                    'file_hash' => $fileHash,
                    'mime_type' => File::mimeType(Storage::disk('public')->path($finalPath)),
                    'filesize' => File::size(Storage::disk('public')->path($finalPath)),
                    'source_url' => $this->sourceUrl,
                ]);

                $this->libraryItem->media_file_id = $mediaFile->id;
                $this->libraryItem->update([
                    'processing_status' => 'completed',
                    'processing_completed_at' => now(),
                ]);
            } else {
                // File already exists, clean up temp file
                Storage::disk('public')->delete($tempPath);

                // Update source URL if this is first time we've seen it from a URL
                if ($this->sourceUrl && ! $mediaFile->source_url) {
                    $mediaFile->source_url = $this->sourceUrl;
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
                'processing_error' => 'Processing failed: '.$e->getMessage(),
            ]);
        }
    }
}

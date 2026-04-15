<?php

use App\Models\LibraryItem;
use App\Models\MediaFile;
use App\Models\User;
use App\ProcessingStatusType;
use App\Services\DuplicateDetectionService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::disk('public')->deleteDirectory('media');
    Storage::disk('public')->makeDirectory('media');

    $this->user = User::factory()->create();
    $this->filePath = 'media/test-hash-optimization.mp3';

    $content = fake()->regexify('[a-z]{1000}');
    Storage::disk('public')->put($this->filePath, $content);
    $this->expectedHash = hash_file('sha256', Storage::disk('public')->path($this->filePath));
});

afterEach(function () {
    Storage::disk('public')->deleteDirectory('media');
});

it('analyzeFileUpload returns the computed hash in its result array', function () {
    $result = DuplicateDetectionService::analyzeFileUpload($this->filePath, $this->user->id);

    expect($result['file_hash'])->toBe($this->expectedHash);
});

it('analyzeFileUpload identifies global duplicate using the hash', function () {
    $mediaFile = MediaFile::factory()->create([
        'user_id' => User::factory()->create()->id,
        'file_hash' => $this->expectedHash,
    ]);

    $result = DuplicateDetectionService::analyzeFileUpload($this->filePath, $this->user->id);

    expect($result['is_global_duplicate'])->toBeTrue();
    expect($result['global_duplicate_media_file']->id)->toBe($mediaFile->id);
    expect($result['file_hash'])->toBe($this->expectedHash);
});

it('analyzeFileUpload identifies user duplicate using the hash', function () {
    $mediaFile = MediaFile::factory()->create([
        'user_id' => $this->user->id,
        'file_hash' => $this->expectedHash,
    ]);

    LibraryItem::factory()->create([
        'user_id' => $this->user->id,
        'media_file_id' => $mediaFile->id,
        'processing_status' => ProcessingStatusType::COMPLETED,
    ]);

    $result = DuplicateDetectionService::analyzeFileUpload($this->filePath, $this->user->id);

    expect($result['is_user_duplicate'])->toBeTrue();
    expect($result['should_link_to_user_duplicate'])->toBeTrue();
    expect($result['file_hash'])->toBe($this->expectedHash);
});

it('analyzeFileUpload returns correct result when no duplicates exist', function () {
    $result = DuplicateDetectionService::analyzeFileUpload($this->filePath, $this->user->id);

    expect($result['is_user_duplicate'])->toBeFalse();
    expect($result['is_global_duplicate'])->toBeFalse();
    expect($result['should_create_new_file'])->toBeTrue();
    expect($result['file_hash'])->toBe($this->expectedHash);
});

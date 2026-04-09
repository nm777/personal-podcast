<?php

use App\Services\MediaProcessing\MediaStorageManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

test('stores file with hash-based naming', function () {
    $manager = app(MediaStorageManager::class);
    $content = 'test content';
    $extension = 'mp3';
    $expectedHash = hash('sha256', $content);

    $result = $manager->storeFile($content, $extension);

    expect($result['file_path'])->toBe('media/'.$expectedHash.'.'.$extension);
    expect($result['file_hash'])->toBe($expectedHash);
    expect($result['filesize'])->toBe(strlen($content));
    Storage::disk('public')->assertExists($result['file_path']);
});

test('stores file with source url', function () {
    $manager = app(MediaStorageManager::class);
    $sourceUrl = 'https://example.com/audio.mp3';

    $result = $manager->storeFile('test content', 'mp3', $sourceUrl);

    expect($result['source_url'])->toBe($sourceUrl);
    Storage::disk('public')->assertExists($result['file_path']);
});

test('cleans up temp file', function () {
    $manager = app(MediaStorageManager::class);
    $tempPath = 'temp-uploads/test-file.mp3';
    Storage::disk('public')->put($tempPath, 'test content');

    $manager->cleanupTempFile($tempPath);

    Storage::disk('public')->assertMissing($tempPath);
});

test('gets file size from storage', function () {
    $manager = app(MediaStorageManager::class);
    $content = 'test content for size check';
    $filePath = 'media/test-file.mp3';
    Storage::disk('public')->put($filePath, $content);

    $size = $manager->getFileSize($filePath);

    expect($size)->toBe(strlen($content));
});

test('returns 0 for non-existent file size', function () {
    $manager = app(MediaStorageManager::class);

    $size = $manager->getFileSize('media/non-existent.mp3');

    expect($size)->toBe(0);
});

test('checks if file exists', function () {
    $manager = app(MediaStorageManager::class);
    $filePath = 'media/test-file.mp3';
    Storage::disk('public')->put($filePath, 'test content');

    $exists = $manager->fileExists($filePath);

    expect($exists)->toBeTrue();
});

test('returns false for non-existent file', function () {
    $manager = app(MediaStorageManager::class);

    $exists = $manager->fileExists('media/non-existent.mp3');

    expect($exists)->toBeFalse();
});

test('generates same hash for identical content', function () {
    $manager = app(MediaStorageManager::class);
    $content = 'identical content';

    $result1 = $manager->storeFile($content, 'mp3');
    $result2 = $manager->storeFile($content, 'mp3');

    expect($result1['file_hash'])->toBe($result2['file_hash']);
    expect($result1['file_path'])->toBe($result2['file_path']);
});

test('generates unique hash for different content', function () {
    $manager = app(MediaStorageManager::class);

    $result1 = $manager->storeFile('content one', 'mp3');
    $result2 = $manager->storeFile('content two', 'mp3');

    expect($result1['file_hash'])->not->toBe($result2['file_hash']);
    expect($result1['file_path'])->not->toBe($result2['file_path']);
});

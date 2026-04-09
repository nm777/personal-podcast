<?php

use App\Models\LibraryItem;
use App\Models\MediaFile;
use App\Models\User;
use App\Services\DuplicateDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

test('calculates file hash from existing file', function () {
    $filePath = 'media/test-audio.mp3';
    $content = 'test audio content';
    Storage::disk('public')->put($filePath, $content);

    $hash = DuplicateDetectionService::calculateFileHash($filePath);

    expect($hash)->toBe(hash('sha256', $content));
});

test('returns null for non-existent file', function () {
    $hash = DuplicateDetectionService::calculateFileHash('media/non-existent.mp3');

    expect($hash)->toBeNull();
});

test('finds global duplicate by hash', function () {
    $filePath = 'media/test-audio.mp3';
    Storage::disk('public')->put($filePath, 'test content');

    $mediaFile = MediaFile::factory()->create([
        'file_hash' => hash('sha256', 'test content'),
    ]);

    $duplicate = DuplicateDetectionService::findGlobalDuplicate($filePath);

    expect($duplicate)->not->toBeNull();
    expect($duplicate->id)->toBe($mediaFile->id);
});

test('returns null when no global duplicate exists', function () {
    $filePath = 'media/unique-audio.mp3';
    Storage::disk('public')->put($filePath, 'unique content');

    $duplicate = DuplicateDetectionService::findGlobalDuplicate($filePath);

    expect($duplicate)->toBeNull();
});

test('finds user duplicate by hash', function () {
    $user = User::factory()->create();
    $filePath = 'media/test-audio.mp3';
    Storage::disk('public')->put($filePath, 'test content');

    $mediaFile = MediaFile::factory()->create([
        'user_id' => $user->id,
        'file_hash' => hash('sha256', 'test content'),
    ]);

    LibraryItem::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
    ]);

    $duplicate = DuplicateDetectionService::findUserDuplicate($filePath, $user->id);

    expect($duplicate)->not->toBeNull();
    expect($duplicate->id)->toBe($mediaFile->id);
});

test('finds url duplicate for user', function () {
    $user = User::factory()->create();
    $sourceUrl = 'https://example.com/audio.mp3';

    $existingItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'source_url' => $sourceUrl,
        'source_type' => 'url',
    ]);

    $duplicate = DuplicateDetectionService::findUrlDuplicateForUser($sourceUrl, $user->id);

    expect($duplicate)->not->toBeNull();
    expect($duplicate->id)->toBe($existingItem->id);
});

test('finds global url duplicate', function () {
    $user = User::factory()->create();
    $sourceUrl = 'https://example.com/audio.mp3';

    $mediaFile = MediaFile::factory()->create([
        'user_id' => $user->id,
        'source_url' => $sourceUrl,
    ]);

    $duplicate = DuplicateDetectionService::findGlobalUrlDuplicate($sourceUrl);

    expect($duplicate)->not->toBeNull();
    expect($duplicate->id)->toBe($mediaFile->id);
});

test('analyzes file upload with user duplicate', function () {
    $user = User::factory()->create();
    $filePath = 'media/test-audio.mp3';
    Storage::disk('public')->put($filePath, 'test content');

    $mediaFile = MediaFile::factory()->create([
        'user_id' => $user->id,
        'file_hash' => hash('sha256', 'test content'),
    ]);

    LibraryItem::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
    ]);

    $result = DuplicateDetectionService::analyzeFileUpload($filePath, $user->id);

    expect($result['is_user_duplicate'])->toBeTrue();
    expect($result['is_global_duplicate'])->toBeTrue();
    expect($result['should_link_to_user_duplicate'])->toBeTrue();
    expect($result['should_create_new_file'])->toBeFalse();
});

test('analyzes file upload with global duplicate only', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $filePath = 'media/test-audio.mp3';
    Storage::disk('public')->put($filePath, 'test content');

    MediaFile::factory()->create([
        'user_id' => $user1->id,
        'file_hash' => hash('sha256', 'test content'),
    ]);

    $result = DuplicateDetectionService::analyzeFileUpload($filePath, $user2->id);

    expect($result['is_user_duplicate'])->toBeFalse();
    expect($result['is_global_duplicate'])->toBeTrue();
    expect($result['should_link_to_global_duplicate'])->toBeTrue();
});

test('analyzes file upload with no duplicates', function () {
    $user = User::factory()->create();
    $filePath = 'media/unique-audio.mp3';
    Storage::disk('public')->put($filePath, 'unique content');

    $result = DuplicateDetectionService::analyzeFileUpload($filePath, $user->id);

    expect($result['is_user_duplicate'])->toBeFalse();
    expect($result['is_global_duplicate'])->toBeFalse();
    expect($result['should_create_new_file'])->toBeTrue();
});

test('analyzes url source with user duplicate', function () {
    $user = User::factory()->create();
    $sourceUrl = 'https://example.com/audio.mp3';

    LibraryItem::factory()->create([
        'user_id' => $user->id,
        'source_url' => $sourceUrl,
        'source_type' => 'url',
    ]);

    $result = DuplicateDetectionService::analyzeUrlSource($sourceUrl, $user->id);

    expect($result['is_user_duplicate'])->toBeTrue();
    expect($result['should_link_to_user_duplicate'])->toBeTrue();
});

test('analyzes url source with global duplicate', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $sourceUrl = 'https://example.com/audio.mp3';

    MediaFile::factory()->create([
        'user_id' => $user1->id,
        'source_url' => $sourceUrl,
    ]);

    $result = DuplicateDetectionService::analyzeUrlSource($sourceUrl, $user2->id);

    expect($result['is_user_duplicate'])->toBeFalse();
    expect($result['is_global_duplicate'])->toBeTrue();
    expect($result['should_link_to_global_duplicate'])->toBeTrue();
});

test('analyzes url source with no duplicates', function () {
    $user = User::factory()->create();

    $result = DuplicateDetectionService::analyzeUrlSource('https://example.com/new.mp3', $user->id);

    expect($result['is_user_duplicate'])->toBeFalse();
    expect($result['is_global_duplicate'])->toBeFalse();
    expect($result['should_create_new_file'])->toBeTrue();
});

test('excludes current library item from url duplicate check', function () {
    $user = User::factory()->create();
    $sourceUrl = 'https://example.com/audio.mp3';

    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'source_url' => $sourceUrl,
        'source_type' => 'url',
    ]);

    $result = DuplicateDetectionService::analyzeUrlSource($sourceUrl, $user->id, $libraryItem->id);

    expect($result['is_user_duplicate'])->toBeFalse();
    expect($result['should_create_new_file'])->toBeTrue();
});

test('detects user media file only edge case', function () {
    $user = User::factory()->create();
    $sourceUrl = 'https://example.com/audio.mp3';

    MediaFile::factory()->create([
        'user_id' => $user->id,
        'source_url' => $sourceUrl,
    ]);

    $result = DuplicateDetectionService::analyzeUrlSource($sourceUrl, $user->id);

    expect($result['is_user_duplicate'])->toBeTrue();
    expect($result['user_media_file_only'])->toBeTrue();
    expect($result['should_link_to_user_media_file'])->toBeTrue();
});

test('handles empty url gracefully', function () {
    $user = User::factory()->create();

    $result = DuplicateDetectionService::analyzeUrlSource('', $user->id);

    expect($result['is_user_duplicate'])->toBeFalse();
    expect($result['is_global_duplicate'])->toBeFalse();
    expect($result['should_create_new_file'])->toBeTrue();
});

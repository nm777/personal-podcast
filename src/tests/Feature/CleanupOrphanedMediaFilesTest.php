<?php

use App\Jobs\CleanupOrphanedMediaFiles;
use App\Models\LibraryItem;
use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::disk('public')->deleteDirectory('media');
    Storage::disk('public')->makeDirectory('media');
});

afterEach(function () {
    Storage::disk('public')->deleteDirectory('media');
});

it('deletes orphaned media files and their storage files', function () {
    $user = User::factory()->create();

    Storage::disk('public')->put('media/orphan-test.mp3', 'fake audio content');

    $orphan = MediaFile::factory()->create([
        'user_id' => $user->id,
        'file_path' => 'media/orphan-test.mp3',
    ]);

    $kept = MediaFile::factory()->create([
        'user_id' => $user->id,
        'file_path' => 'media/kept-test.mp3',
    ]);
    LibraryItem::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $kept->id,
    ]);

    (new CleanupOrphanedMediaFiles)->handle();

    expect(MediaFile::find($orphan->id))->toBeNull();
    expect(MediaFile::find($kept->id))->not->toBeNull();
    expect(Storage::disk('public')->exists('media/orphan-test.mp3'))->toBeFalse();
});

it('handles more than 100 orphaned files without memory issues', function () {
    $user = User::factory()->create();

    for ($i = 0; $i < 110; $i++) {
        Storage::disk('public')->put("media/orphan-{$i}.mp3", "content {$i}");

        MediaFile::factory()->create([
            'user_id' => $user->id,
            'file_path' => "media/orphan-{$i}.mp3",
        ]);
    }

    expect(MediaFile::count())->toBe(110);

    (new CleanupOrphanedMediaFiles)->handle();

    expect(MediaFile::count())->toBe(0);
});

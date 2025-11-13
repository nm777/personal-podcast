<?php

use App\Models\LibraryItem;
use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

it('displays library page for authenticated users', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/library');

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page->component('Library/Index')
    );
});

it('shows only authenticated user library items', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $mediaFile = MediaFile::factory()->create();

    $userItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
        'title' => 'User Item',
    ]);

    $otherUserItem = LibraryItem::factory()->create([
        'user_id' => $otherUser->id,
        'media_file_id' => $mediaFile->id,
        'title' => 'Other User Item',
    ]);

    $response = $this->actingAs($user)->get('/library');

    $response->assertInertia(
        fn ($page) => $page->component('Library/Index')
            ->has('libraryItems', 1)
            ->where('libraryItems.0.title', 'User Item')
    );
});

it('can upload a media file', function () {
    Storage::fake('public');
    Queue::fake();

    $user = User::factory()->create();

    $file = UploadedFile::fake()->create('test-audio.mp3', 1000, 'audio/mpeg');

    $response = $this->actingAs($user)->post('/library', [
        'title' => 'Test Audio',
        'description' => 'Test Description',
        'file' => $file,
    ]);

    $response->assertRedirect('/library');
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('library_items', [
        'user_id' => $user->id,
        'title' => 'Test Audio',
        'description' => 'Test Description',
        'source_type' => 'upload',
    ]);

    Queue::assertPushed(\App\Jobs\ProcessMediaFile::class);
});

it('validates file upload requirements', function () {
    $user = User::factory()->create();

    // Test missing file
    $response = $this->actingAs($user)->post('/library', [
        'title' => 'Test Audio',
    ]);

    $response->assertSessionHasErrors('file');

    // Test invalid file type
    $file = UploadedFile::fake()->create('test.txt', 1000, 'text/plain');

    $response = $this->actingAs($user)->post('/library', [
        'title' => 'Test Audio',
        'file' => $file,
    ]);

    $response->assertSessionHasErrors('file');
});

it('validates source_type field for web requests', function () {
    $user = User::factory()->create();

    // Test invalid source_type
    $response = $this->actingAs($user)->post('/library', [
        'title' => 'Test Audio',
        'source_type' => 'invalid-type',
        'url' => 'https://example.com/audio.mp3',
    ]);

    $response->assertSessionHasErrors('source_type');
});

it('uses consolidated validation rules from web form request', function () {
    $request = new \App\Http\Requests\LibraryItemRequest;

    $rules = $request->rules();

    expect($rules)->toHaveKey('title');
    expect($rules)->toHaveKey('source_type');
    expect($rules)->toHaveKey('file');
    expect($rules)->toHaveKey('url');
    expect($rules)->toHaveKey('source_url');
    expect($rules)->toHaveKey('description');

    expect($rules['title'])->toContain('required');
    expect($rules['source_type'])->toContain('in:upload,url,youtube');
    expect($rules['file'])->toContain('required_without_all:source_url,url');
    expect($rules['url'])->toContain('required_without_all:source_url,file');
    expect($rules['source_url'])->toContain('required_without_all:file,url');
});

it('maintains backward compatibility with url field', function () {
    $user = User::factory()->create();

    // Test old url field still works
    $response = $this->actingAs($user)->post('/library', [
        'title' => 'Test Audio',
        'url' => 'https://example.com/audio.mp3',
    ]);

    $response->assertRedirect('/library');
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('library_items', [
        'user_id' => $user->id,
        'title' => 'Test Audio',
        'source_type' => 'url',
    ]);
});

it('processes new source_url field correctly', function () {
    $user = User::factory()->create();

    // Test new source_url field works
    $response = $this->actingAs($user)->post('/library', [
        'title' => 'Test Audio',
        'source_type' => 'url',
        'source_url' => 'https://example.com/audio.mp3',
    ]);

    $response->assertRedirect('/library');
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('library_items', [
        'user_id' => $user->id,
        'title' => 'Test Audio',
        'source_type' => 'url',
    ]);
});

it('detects duplicate file uploads by hash', function () {
    Storage::fake('public');
    Queue::fake();

    $user = User::factory()->create();

    // Create first media file with specific hash
    $mediaFile = MediaFile::factory()->create([
        'user_id' => $user->id,
        'file_hash' => hash('sha256', 'test audio content'),
        'file_path' => 'media/existing-file.mp3',
    ]);

    // Create a library item that references this media file
    LibraryItem::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
        'title' => 'Original File',
        'source_type' => 'upload',
        'processing_status' => \App\ProcessingStatusType::COMPLETED,
    ]);

    // Create actual file in storage so duplicate detection works
    Storage::disk('public')->put($mediaFile->file_path, 'test audio content');

    // Create a fake file with the same content and manually store it
    $file = UploadedFile::fake()->createWithContent('duplicate-audio.mp3', 'test audio content');
    $tempPath = $file->store('temp-uploads');

    // Manually put the file content in storage since UploadedFile::fake() doesn't work with store()
    Storage::disk('public')->put($tempPath, 'test audio content');

    $response = $this->actingAs($user)->post('/library', [
        'title' => 'Duplicate File',
        'description' => 'This should be detected as duplicate',
        'file' => $file,
    ]);

    $response->assertRedirect('/library');
    $response->assertSessionHas('success', 'Duplicate file detected. This file already exists in your library and will be removed automatically in 5 minutes.');

    // Should create library item linked to existing media file and marked as duplicate
    $this->assertDatabaseHas('library_items', [
        'user_id' => $user->id,
        'title' => 'Duplicate File',
        'source_type' => 'upload',
        'media_file_id' => $mediaFile->id,
        'is_duplicate' => true,
    ]);

    // Should schedule cleanup for duplicate
    Queue::assertPushed(\App\Jobs\CleanupDuplicateLibraryItem::class);
});

it('processes non-duplicate file uploads normally', function () {
    Storage::fake('public');
    Queue::fake();

    $user = User::factory()->create();

    // Create existing media file with different hash
    MediaFile::factory()->create([
        'file_hash' => hash('sha256', 'different content'),
        'file_path' => 'media/different-file.mp3',
    ]);

    // Create a fake file with different content
    $file = UploadedFile::fake()->createWithContent('new-audio.mp3', 'new unique content');

    $response = $this->actingAs($user)->post('/library', [
        'title' => 'New File',
        'description' => 'This should be processed normally',
        'file' => $file,
    ]);

    $response->assertRedirect('/library');
    $response->assertSessionHas('success', 'Media file uploaded successfully. Processing...');

    // Should create library item without media_file_id initially
    $this->assertDatabaseHas('library_items', [
        'user_id' => $user->id,
        'title' => 'New File',
        'media_file_id' => null,
        'source_type' => 'upload',
    ]);

    // Job should be dispatched for new files
    Queue::assertPushed(\App\Jobs\ProcessMediaFile::class);
});

it('MediaFile model can find duplicates by hash', function () {
    Storage::fake('public');

    // Create a media file with known hash
    $knownHash = hash('sha256', 'test content');
    $mediaFile = MediaFile::factory()->create([
        'file_hash' => $knownHash,
    ]);

    // Test findByHash method
    $found = MediaFile::findByHash($knownHash);
    expect($found)->not->toBeNull();
    expect($found->id)->toBe($mediaFile->id);

    // Test with non-existent hash
    $notFound = MediaFile::findByHash('nonexistenthash');
    expect($notFound)->toBeNull();
});

it('MediaFile model can check file duplicates', function () {
    Storage::fake('public');

    // Create a fake file
    $content = 'test audio content';
    $tempPath = 'temp/test-file.mp3';
    Storage::disk('public')->put($tempPath, $content);
    $fullPath = Storage::disk('public')->path($tempPath);

    // Create media file with same hash
    $mediaFile = MediaFile::factory()->create([
        'file_hash' => hash('sha256', $content),
    ]);

    // Test duplicate detection
    $duplicate = MediaFile::isDuplicate($tempPath);
    expect($duplicate)->not->toBeNull();
    expect($duplicate->id)->toBe($mediaFile->id);

    // Test with non-existent file
    $nonDuplicate = MediaFile::isDuplicate('/non/existent/file.mp3');
    expect($nonDuplicate)->toBeNull();

    // Clean up
    Storage::disk('public')->delete($tempPath);
});

it('marks duplicate library items and schedules cleanup', function () {
    Storage::fake('public');
    Queue::fake();

    $user = User::factory()->create();

    // Create existing media file
    $mediaFile = MediaFile::factory()->create([
        'user_id' => $user->id,
        'file_hash' => hash('sha256', 'duplicate content'),
        'file_path' => 'media/existing-file.mp3',
    ]);

    // Create existing library item that references the media file
    LibraryItem::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
        'title' => 'Original File',
        'source_type' => 'upload',
        'processing_status' => \App\ProcessingStatusType::COMPLETED,
    ]);

    // Create library item for duplicate upload
    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'title' => 'Duplicate Upload',
        'source_type' => 'upload',
    ]);

    // Create temp file with same content
    $tempPath = 'temp/duplicate-upload.mp3';
    Storage::disk('public')->put($tempPath, 'duplicate content');

    // Process the file
    $job = new \App\Jobs\ProcessMediaFile($libraryItem, null, $tempPath);
    $job->handle();

    $libraryItem->refresh();

    // Should be marked as duplicate
    expect($libraryItem->is_duplicate)->toBeTrue();
    expect($libraryItem->duplicate_detected_at)->not->toBeNull();
    expect($libraryItem->media_file_id)->toBe($mediaFile->id);

    // Should schedule cleanup job
    Queue::assertPushed(\App\Jobs\CleanupDuplicateLibraryItem::class, function ($job) use ($libraryItem) {
        return $job->libraryItem->id === $libraryItem->id;
    });
});

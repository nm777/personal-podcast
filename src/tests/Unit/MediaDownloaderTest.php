<?php

use App\Services\MediaProcessing\MediaDownloader;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

test('downloads media file successfully', function () {
    $url = 'https://example.com/audio.mp3';
    $content = 'ID3'.str_repeat("\x00", 100).'audio';

    Http::fake([$url => Http::response($content, 200)]);

    $result = (new MediaDownloader)->downloadFromUrl($url);

    expect($result)->toBe($content);
});

test('throws exception for failed http request', function () {
    $url = 'https://example.com/audio.mp3';

    Http::fake([$url => Http::response('Not Found', 404)]);

    expect(fn () => (new MediaDownloader)->downloadFromUrl($url))
        ->toThrow(Exception::class, 'Failed to download file: HTTP 404');
});

test('throws exception for empty content', function () {
    $url = 'https://example.com/audio.mp3';

    Http::fake([$url => Http::response('', 200)]);

    expect(fn () => (new MediaDownloader)->downloadFromUrl($url))
        ->toThrow(Exception::class, 'Downloaded file is empty');
});

test('throws exception for html content without redirect', function () {
    $url = 'https://example.com/audio.mp3';

    Http::fake([$url => Http::response('<!DOCTYPE html><html><body>Error</body></html>', 200)]);

    expect(fn () => (new MediaDownloader)->downloadFromUrl($url))
        ->toThrow(Exception::class, 'Download failed: Got HTML content instead of media file');
});

test('handles javascript redirect', function () {
    $url = 'https://example.com/download';
    $redirectUrl = 'https://example.com/actual.mp3';
    $audioContent = 'ID3'.str_repeat("\x00", 100).'audio';

    Http::fake([
        $url => Http::response('<html><script>window.location.replace("'.$redirectUrl.'")</script></html>', 200),
        $redirectUrl => Http::response($audioContent, 200),
    ]);

    $result = (new MediaDownloader)->downloadFromUrl($url);

    expect($result)->toBe($audioContent);
});

test('converts relative redirect url to absolute', function () {
    $url = 'https://example.com/download';
    $absoluteUrl = 'https://example.com/files/audio.mp3';
    $audioContent = 'ID3'.str_repeat("\x00", 100).'audio';

    Http::fake([
        $url => Http::response('<html><script>window.location.replace("/files/audio.mp3")</script></html>', 200),
        $absoluteUrl => Http::response($audioContent, 200),
    ]);

    $result = (new MediaDownloader)->downloadFromUrl($url);

    expect($result)->toBe($audioContent);
});

test('validates mp3 with id3 tag', function () {
    $url = 'https://example.com/audio.mp3';
    $content = 'ID3'.str_repeat("\0", 1000).'audio';

    Http::fake([$url => Http::response($content, 200)]);

    $result = (new MediaDownloader)->downloadFromUrl($url);

    expect($result)->toBe($content);
});

test('throws exception for invalid media content', function () {
    $url = 'https://example.com/file.txt';

    Http::fake([$url => Http::response(str_repeat('invalid text content', 100), 200)]);

    expect(fn () => (new MediaDownloader)->downloadFromUrl($url))
        ->toThrow(Exception::class);
});

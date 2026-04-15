<?php

use App\Services\MediaProcessing\MediaDownloader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

describe('Small file media validation', function () {
    it('rejects files under 100 bytes with invalid signature', function () {
        Http::fake([
            '*' => Http::response(str_repeat('x', 50), 200, [
                'Content-Type' => 'audio/mpeg',
            ]),
        ]);

        $downloader = app(MediaDownloader::class);

        expect(fn () => $downloader->downloadFromUrl('https://example.com/tiny.mp3'))
            ->toThrow(Exception::class);
    });

    it('rejects 1-byte invalid file', function () {
        Http::fake([
            '*' => Http::response('X', 200, [
                'Content-Type' => 'audio/mpeg',
            ]),
        ]);

        $downloader = app(MediaDownloader::class);

        expect(fn () => $downloader->downloadFromUrl('https://example.com/tiny.mp3'))
            ->toThrow(Exception::class);
    });

    it('accepts small files with valid MP3 ID3 signature', function () {
        $content = 'ID3'.str_repeat("\x00", 47);

        Http::fake([
            '*' => Http::response($content, 200, [
                'Content-Type' => 'audio/mpeg',
            ]),
        ]);

        $downloader = app(MediaDownloader::class);
        $result = $downloader->downloadFromUrl('https://example.com/valid.mp3');

        expect($result)->toBeString();
    });
});

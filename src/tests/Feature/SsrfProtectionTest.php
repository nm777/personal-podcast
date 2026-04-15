<?php

use App\Services\MediaProcessing\MediaDownloader;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

describe('SSRF protection in MediaDownloader', function () {
    beforeEach(function () {
        Storage::fake('public');
    });

    $blockedUrls = [
        'AWS metadata' => 'http://169.254.169.254/latest/meta-data/',
        'localhost' => 'http://localhost/secret',
        'localhost with port' => 'http://localhost:8080/admin',
        'loopback 127.0.0.1' => 'http://127.0.0.1/secret',
        'private 10.x' => 'http://10.0.0.1/internal',
        'private 172.16.x' => 'http://172.16.0.1/internal',
        'private 192.168.x' => 'http://192.168.1.1/router',
        'link-local' => 'http://169.254.0.1/test',
    ];

    foreach ($blockedUrls as $name => $url) {
        it("blocks {$name} URL", function () use ($url) {
            Http::fake([
                $url => Http::response('secret data', 200),
            ]);

            $downloader = new MediaDownloader;

            expect(fn () => $downloader->downloadFromUrl($url))
                ->toThrow(InvalidArgumentException::class, 'private');
        });
    }

    it('allows public URLs', function () {
        Http::fake([
            'https://example.com/audio.mp3' => Http::response('ID3'.str_repeat("\x00", 200), 200, [
                'Content-Type' => 'audio/mpeg',
            ]),
        ]);

        $downloader = new MediaDownloader;
        $result = $downloader->downloadFromUrl('https://example.com/audio.mp3');

        expect($result)->not->toBeEmpty();
    });
});

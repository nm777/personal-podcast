<?php

use App\Services\SourceProcessors\SourceProcessorFactory;
use App\Services\SourceProcessors\UnifiedSourceProcessor;
use App\Services\YouTubeUrlValidator;

it('creates unified processor with upload strategy', function () {
    $processor = SourceProcessorFactory::create('upload');

    expect($processor)->toBeInstanceOf(UnifiedSourceProcessor::class);
});

it('creates unified processor with url strategy', function () {
    $processor = SourceProcessorFactory::create('url');

    expect($processor)->toBeInstanceOf(UnifiedSourceProcessor::class);
});

it('creates unified processor with youtube strategy', function () {
    $processor = SourceProcessorFactory::create('youtube');

    expect($processor)->toBeInstanceOf(UnifiedSourceProcessor::class);
});

it('throws exception for unsupported source type', function () {
    expect(fn () => SourceProcessorFactory::create('unsupported'))
        ->toThrow(InvalidArgumentException::class, 'Unsupported source type: unsupported');
});

it('validates youtube url successfully', function () {
    $result = SourceProcessorFactory::validate('youtube', 'https://www.youtube.com/watch?v=test123');

    expect($result)->toBeNull();
});

it('validates non-youtube url successfully', function () {
    $result = SourceProcessorFactory::validate('url', 'https://example.com/audio.mp3');

    expect($result)->toBeNull();
});

it('validates upload successfully', function () {
    $result = SourceProcessorFactory::validate('upload', null);

    expect($result)->toBeNull();
});

it('detects invalid youtube url', function () {
    // Test the actual YouTubeUrlValidator logic directly
    $isValid = YouTubeUrlValidator::isValidYouTubeUrl('invalid-url');

    expect($isValid)->toBeFalse();
});

it('validates mobile youtube url successfully', function () {
    $result = SourceProcessorFactory::validate('youtube', 'https://m.youtube.com/watch?v=test123');

    expect($result)->toBeNull();
});

it('validates youtube url with mobile subdomain', function () {
    $isValid = YouTubeUrlValidator::isValidYouTubeUrl('https://m.youtube.com/watch?v=test123');

    expect($isValid)->toBeTrue();
});

it('validates youtube live url', function () {
    $isValid = YouTubeUrlValidator::isValidYouTubeUrl('https://youtube.com/live/gbW9_DxgBsE');

    expect($isValid)->toBeTrue();
});

it('extracts video id from youtube live url', function () {
    $videoId = YouTubeUrlValidator::extractVideoId('https://youtube.com/live/gbW9_DxgBsE?si=d-c_3pQ3VbQS9VNk');

    expect($videoId)->toBe('gbW9_DxgBsE');
});

it('extracts video id from youtube live url with www', function () {
    $videoId = YouTubeUrlValidator::extractVideoId('https://www.youtube.com/live/abc123');

    expect($videoId)->toBe('abc123');
});

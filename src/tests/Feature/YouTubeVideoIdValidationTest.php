<?php

use App\Services\YouTubeUrlValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('YouTube video ID validation', function () {
    it('rejects video IDs shorter than 11 characters', function () {
        expect(YouTubeUrlValidator::extractVideoId('https://www.youtube.com/watch?v=abc'))->toBeNull();
    });

    it('rejects video IDs longer than 11 characters', function () {
        expect(YouTubeUrlValidator::extractVideoId('https://www.youtube.com/watch?v=dQw4w9WgXcQextra'))->toBeNull();
    });

    it('rejects video IDs with special characters', function () {
        expect(YouTubeUrlValidator::extractVideoId('https://www.youtube.com/watch?v=dQw4w9WgXc!'))->toBeNull();
    });

    it('accepts valid 11-character video IDs', function () {
        expect(YouTubeUrlValidator::extractVideoId('https://www.youtube.com/watch?v=dQw4w9WgXcQ'))->toBe('dQw4w9WgXcQ');
    });

    it('accepts valid video IDs with underscores and dashes', function () {
        expect(YouTubeUrlValidator::extractVideoId('https://www.youtube.com/watch?v=abc_DEF-GHI'))->toBe('abc_DEF-GHI');
    });
});

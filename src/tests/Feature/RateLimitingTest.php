<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

describe('Rate limiting on sensitive endpoints', function () {
    it('applies throttle middleware to RSS feed endpoint', function () {
        $route = Route::getRoutes()->getByName('rss.show');
        expect($route)->not->toBeNull();
        expect($route->gatherMiddleware())->toContain('throttle:120,1');
    });

    it('applies throttle middleware to media file endpoint', function () {
        $route = Route::getRoutes()->getByName('files.show');
        expect($route)->not->toBeNull();
        expect($route->gatherMiddleware())->toContain('throttle:60,1');
    });

    it('applies throttle middleware to YouTube video info endpoint', function () {
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => in_array('GET', $r->methods()) && str_contains($r->uri(), 'youtube/video-info'));
        expect($route)->not->toBeNull();
        expect($route->gatherMiddleware())->toContain('throttle:30,1');
    });
});

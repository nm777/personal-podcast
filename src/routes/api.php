<?php

use App\Http\Controllers\Api\FeedController;
use App\Http\Controllers\Api\LibraryController;
use App\Http\Controllers\Api\UrlDuplicateCheckController;
use App\Http\Controllers\Api\YouTubeController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('library', [LibraryController::class, 'index']);
    Route::post('library', [LibraryController::class, 'store']);
    Route::post('feeds/{feed}/items', [FeedController::class, 'addItems']);
    Route::delete('feeds/{feed}/items', [FeedController::class, 'removeItems']);
    Route::get('youtube/video-info/{videoId}', [YouTubeController::class, 'getVideoInfo']);
    Route::post('check-url', [UrlDuplicateCheckController::class, 'check']);
});

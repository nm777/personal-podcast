<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Application Constants
    |--------------------------------------------------------------------------
    |
    | Hard-coded values used throughout the application.
    | Centralized here for easier maintenance and configuration.
    |
    */

    'duplicate' => [
        'cleanup_delay_minutes' => 5,
    ],

    'processing' => [
        'start_delay_seconds' => 30,
    ],

    'cache' => [
        'youtube_info_duration_seconds' => 30 * 24 * 60 * 60, // 30 days
        'rss_feed_duration_seconds' => 15 * 60, // 15 minutes
    ],
];

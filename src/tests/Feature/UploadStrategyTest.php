<?php

use App\Services\SourceProcessors\UploadStrategy;

it('UploadStrategy uses config value in duplicate message', function () {
    config(['constants.duplicate.cleanup_delay_minutes' => 10]);

    $strategy = new UploadStrategy;
    $message = $strategy->getSuccessMessage(true);

    expect($message)->toContain('10 minutes');
    expect($message)->not->toContain('5 minutes');
});

it('UploadStrategy returns correct non-duplicate message', function () {
    $strategy = new UploadStrategy;
    $message = $strategy->getSuccessMessage(false);

    expect($message)->toBe('Media file uploaded successfully. Processing...');
});

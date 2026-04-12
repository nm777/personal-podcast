<?php

it('ProcessingStatusType enum exists at App\\Enums namespace', function () {
    $enum = new \ReflectionClass(\App\Enums\ProcessingStatusType::class);
    expect($enum->isEnum())->toBeTrue();
    expect($enum->getNamespaceName())->toBe('App\\Enums');
});

it('ProcessingStatusType has all expected cases', function () {
    $cases = \App\Enums\ProcessingStatusType::cases();
    expect($cases)->toHaveCount(4);

    $values = collect($cases)->map->value->all();
    expect($values)->toBe(['pending', 'processing', 'completed', 'failed']);
});

it('ProcessingStatusType methods work correctly', function () {
    expect(\App\Enums\ProcessingStatusType::PENDING->isPending())->toBeTrue();
    expect(\App\Enums\ProcessingStatusType::PROCESSING->isProcessing())->toBeTrue();
    expect(\App\Enums\ProcessingStatusType::COMPLETED->hasCompleted())->toBeTrue();
    expect(\App\Enums\ProcessingStatusType::FAILED->hasFailed())->toBeTrue();
    expect(\App\Enums\ProcessingStatusType::FAILED->getDisplayName())->toBe('Failed');
});

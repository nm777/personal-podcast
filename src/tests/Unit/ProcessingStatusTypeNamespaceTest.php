<?php

use App\Enums\ProcessingStatusType;

it('ProcessingStatusType enum exists at App\\Enums namespace', function () {
    $enum = new ReflectionClass(ProcessingStatusType::class);
    expect($enum->isEnum())->toBeTrue();
    expect($enum->getNamespaceName())->toBe('App\\Enums');
});

it('ProcessingStatusType has all expected cases', function () {
    $cases = ProcessingStatusType::cases();
    expect($cases)->toHaveCount(4);

    $values = collect($cases)->map->value->all();
    expect($values)->toBe(['pending', 'processing', 'completed', 'failed']);
});

it('ProcessingStatusType methods work correctly', function () {
    expect(ProcessingStatusType::PENDING->isPending())->toBeTrue();
    expect(ProcessingStatusType::PROCESSING->isProcessing())->toBeTrue();
    expect(ProcessingStatusType::COMPLETED->hasCompleted())->toBeTrue();
    expect(ProcessingStatusType::FAILED->hasFailed())->toBeTrue();
    expect(ProcessingStatusType::FAILED->getDisplayName())->toBe('Failed');
});

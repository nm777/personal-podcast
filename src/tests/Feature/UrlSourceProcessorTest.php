<?php

use App\Models\User;
use App\Services\SourceProcessors\LibraryItemFactory;
use App\Services\SourceProcessors\UrlSourceProcessor;

describe('UrlSourceProcessor', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    it('can be instantiated with dependencies', function () {
        $libraryItemFactory = new LibraryItemFactory;
        $strategy = Mockery::mock(\App\Services\SourceProcessors\SourceStrategyInterface::class);

        $processor = new UrlSourceProcessor($libraryItemFactory, $strategy);

        expect($processor)->toBeInstanceOf(UrlSourceProcessor::class);
    });

    it('has correct method signatures', function () {
        $libraryItemFactory = new LibraryItemFactory;
        $strategy = Mockery::mock(\App\Services\SourceProcessors\SourceStrategyInterface::class);

        $processor = new UrlSourceProcessor($libraryItemFactory, $strategy);

        $reflection = new ReflectionClass($processor);
        expect($reflection->hasMethod('process'))->toBeTrue();

        $method = $reflection->getMethod('process');
        expect($method->getNumberOfParameters())->toBe(3);
        expect($method->getNumberOfRequiredParameters())->toBe(3);
    });

    it('requires strategy dependency', function () {
        $libraryItemFactory = new LibraryItemFactory;

        // Constructor should require strategy parameter
        $reflection = new ReflectionClass(UrlSourceProcessor::class);
        $constructor = $reflection->getConstructor();
        expect($constructor->getNumberOfParameters())->toBe(2);
        expect($constructor->getNumberOfRequiredParameters())->toBe(2);
    });
});

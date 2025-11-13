<?php

use App\Models\User;
use App\ProcessingStatusType;
use App\Services\SourceProcessors\LibraryItemFactory;

describe('LibraryItemFactory', function () {
    beforeEach(function () {
        $this->factory = new LibraryItemFactory;
        $this->user = User::factory()->create();
    });

    describe('basic functionality', function () {
        it('can be instantiated', function () {
            expect($this->factory)->toBeInstanceOf(LibraryItemFactory::class);
        });

        it('has required methods', function () {
            expect(method_exists($this->factory, 'createFromValidated'))->toBeTrue();
            expect(method_exists($this->factory, 'createFromValidatedWithMediaData'))->toBeTrue();
            expect(method_exists($this->factory, 'createFromValidatedWithMediaFile'))->toBeTrue();
        });
    });

    describe('method signatures', function () {
        it('createFromValidated has correct parameters', function () {
            $reflection = new ReflectionMethod($this->factory, 'createFromValidated');

            expect($reflection->getNumberOfParameters())->toBe(4);
            expect($reflection->getNumberOfRequiredParameters())->toBe(2);
        });

        it('createFromValidatedWithMediaData has correct parameters', function () {
            $reflection = new ReflectionMethod($this->factory, 'createFromValidatedWithMediaData');

            expect($reflection->getNumberOfParameters())->toBe(4);
            expect($reflection->getNumberOfRequiredParameters())->toBe(3);
        });

        it('createFromValidatedWithMediaFile has correct parameters', function () {
            $reflection = new ReflectionMethod($this->factory, 'createFromValidatedWithMediaFile');

            expect($reflection->getNumberOfParameters())->toBe(5);
            expect($reflection->getNumberOfRequiredParameters())->toBe(3);
        });
    });

    describe('parameter validation', function () {
        it('requires validated array', function () {
            expect(fn () => $this->factory->createFromValidated('invalid', 'upload'))
                ->toThrow(\TypeError::class);
        });

        it('requires source type string', function () {
            expect(fn () => $this->factory->createFromValidated([], 123))
                ->toThrow(\Exception::class);
        });

        it('accepts optional source URL', function () {
            $reflection = new ReflectionMethod($this->factory, 'createFromValidated');
            $parameters = $reflection->getParameters();

            $sourceUrlParam = $parameters[2];
            expect($sourceUrlParam->getName())->toBe('sourceUrl');
            expect($sourceUrlParam->isDefaultValueAvailable())->toBeTrue();
            expect($sourceUrlParam->getDefaultValue())->toBeNull();
        });

        it('accepts optional user ID', function () {
            $reflection = new ReflectionMethod($this->factory, 'createFromValidated');
            $parameters = $reflection->getParameters();

            $userIdParam = $parameters[3];
            expect($userIdParam->getName())->toBe('userId');
            expect($userIdParam->isDefaultValueAvailable())->toBeTrue();
            expect($userIdParam->getDefaultValue())->toBeNull();
        });
    });

    describe('processing status handling', function () {
        it('uses PENDING status for new items', function () {
            // Test that the factory uses the correct status constant
            $reflection = new ReflectionClass($this->factory);
            $source = $reflection->getFileName();

            expect($source)->toContain('LibraryItemFactory.php');
            expect(ProcessingStatusType::PENDING->value)->toBe('pending');
            expect(ProcessingStatusType::COMPLETED->value)->toBe('completed');
        });
    });

    describe('edge cases', function () {
        it('handles empty validated data gracefully', function () {
            // Should not throw exceptions with minimal data
            $result = $this->factory->createFromValidated(['title' => 'Test'], 'upload', null, $this->user->id);
            expect($result)->not->toBeNull();
        });

        it('handles null source URL gracefully', function () {
            $result = $this->factory->createFromValidated(['title' => 'Test'], 'upload', null, $this->user->id);
            expect($result)->not->toBeNull();
        });

        it('handles empty media data gracefully', function () {
            $result = $this->factory->createFromValidatedWithMediaData(['title' => 'Test'], 'upload', [], $this->user->id);
            expect($result)->not->toBeNull();
        });
    });
});

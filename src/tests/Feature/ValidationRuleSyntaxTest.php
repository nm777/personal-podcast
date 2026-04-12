<?php

it('LibraryItemRequest rules use array format consistently', function () {
    $request = new \App\Http\Requests\LibraryItemRequest();
    $rules = $request->rules();

    foreach ($rules as $field => $rule) {
        if (is_string($rule)) {
            expect(false)->toBeTrue("Field '{$field}' uses string rule: {$rule}");
        }
    }

    expect(true)->toBeTrue();
});

it('UpdateLibraryItemRequest rules use array format consistently', function () {
    $request = new \App\Http\Requests\UpdateLibraryItemRequest();
    $rules = $request->rules();

    foreach ($rules as $field => $rule) {
        if (is_string($rule)) {
            expect(false)->toBeTrue("Field '{$field}' uses string rule: {$rule}");
        }
    }

    expect(true)->toBeTrue();
});

it('FeedRequest rules use array format consistently', function () {
    $request = new \App\Http\Requests\FeedRequest();
    $rules = $request->rules();

    foreach ($rules as $field => $rule) {
        if (is_string($rule)) {
            expect(false)->toBeTrue("Field '{$field}' uses string rule: {$rule}");
        }
    }

    expect(true)->toBeTrue();
});

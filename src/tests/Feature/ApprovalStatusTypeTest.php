<?php

it('ApprovalStatusType enum exists with correct cases', function () {
    $cases = \App\Enums\ApprovalStatusType::cases();
    expect($cases)->toHaveCount(3);

    $values = collect($cases)->map->value->all();
    expect($values)->toBe(['pending', 'approved', 'rejected']);
});

it('User model uses ApprovalStatusType enum for approval_status', function () {
    $user = \App\Models\User::factory()->create([
        'approval_status' => 'approved',
    ]);

    expect($user->approval_status)->toBeInstanceOf(\App\Enums\ApprovalStatusType::class);
    expect($user->approval_status)->toBe(\App\Enums\ApprovalStatusType::APPROVED);
});

it('User model returns correct enum for pending status', function () {
    $user = \App\Models\User::factory()->create([
        'approval_status' => 'pending',
    ]);

    expect($user->approval_status)->toBe(\App\Enums\ApprovalStatusType::PENDING);
    expect($user->isPending())->toBeTrue();
});

it('User model returns correct enum for rejected status', function () {
    $user = \App\Models\User::factory()->create([
        'approval_status' => 'rejected',
    ]);

    expect($user->approval_status)->toBe(\App\Enums\ApprovalStatusType::REJECTED);
    expect($user->isRejected())->toBeTrue();
});

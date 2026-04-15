<?php

use App\Enums\ApprovalStatusType;
use App\Models\User;

it('ApprovalStatusType enum exists with correct cases', function () {
    $cases = ApprovalStatusType::cases();
    expect($cases)->toHaveCount(3);

    $values = collect($cases)->map->value->all();
    expect($values)->toBe(['pending', 'approved', 'rejected']);
});

it('User model uses ApprovalStatusType enum for approval_status', function () {
    $user = User::factory()->create([
        'approval_status' => 'approved',
    ]);

    expect($user->approval_status)->toBeInstanceOf(ApprovalStatusType::class);
    expect($user->approval_status)->toBe(ApprovalStatusType::APPROVED);
});

it('User model returns correct enum for pending status', function () {
    $user = User::factory()->create([
        'approval_status' => 'pending',
    ]);

    expect($user->approval_status)->toBe(ApprovalStatusType::PENDING);
    expect($user->isPending())->toBeTrue();
});

it('User model returns correct enum for rejected status', function () {
    $user = User::factory()->create([
        'approval_status' => 'rejected',
    ]);

    expect($user->approval_status)->toBe(ApprovalStatusType::REJECTED);
    expect($user->isRejected())->toBeTrue();
});

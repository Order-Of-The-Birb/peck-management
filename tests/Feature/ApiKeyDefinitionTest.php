<?php

use App\Models\ApiKey;
use App\Models\User;

test('api key factory creates a valid record with owner relationship', function () {
    $apiKey = ApiKey::factory()->create();

    expect($apiKey->owner)->toBeInt()
        ->and($apiKey->key)->toBeString()
        ->and($apiKey->key)->toHaveLength(64)
        ->and($apiKey->ownerUser)->toBeInstanceOf(User::class)
        ->and($apiKey->ownerUser?->is(User::query()->findOrFail($apiKey->owner)))->toBeTrue();
});

test('api key model resolves records by owner primary key', function () {
    $owner = User::query()->create([
        'name' => fake()->name(),
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
    ]);

    $apiKey = ApiKey::factory()->create([
        'owner' => $owner->id,
    ]);

    $resolvedApiKey = ApiKey::query()->findOrFail($owner->id);

    expect($resolvedApiKey->is($apiKey))->toBeTrue()
        ->and($resolvedApiKey->owner)->toBe($owner->id);
});

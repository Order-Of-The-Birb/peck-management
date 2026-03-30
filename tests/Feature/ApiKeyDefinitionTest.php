<?php

use App\Models\ApiKey;
use App\Models\User;

test('api key factory creates a valid record with owner relationship', function () {
    $apiKey = ApiKey::factory()->create();

    expect($apiKey->owner)->toBeInt()
        ->and($apiKey->key)->toBeString()
        ->and($apiKey->key)->toHaveLength(64)
        ->and($apiKey->key_prefix)->toBeString()
        ->and($apiKey->key_prefix)->toHaveLength(12)
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

test('issuing an api key stores only hash and resolves by plain token', function () {
    $owner = User::query()->create([
        'name' => fake()->name(),
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
    ]);

    $plainToken = ApiKey::issueForOwner($owner->id);
    $apiKey = ApiKey::query()->findOrFail($owner->id);

    expect($plainToken)->toBeString()->toStartWith('pmk_')
        ->and($apiKey->key)->toBe(ApiKey::hashToken($plainToken))
        ->and($apiKey->key)->not->toBe($plainToken)
        ->and($apiKey->key_prefix)->toBe(ApiKey::prefixFromToken($plainToken))
        ->and(ApiKey::findByPlainToken($plainToken)?->owner)->toBe($owner->id);
});

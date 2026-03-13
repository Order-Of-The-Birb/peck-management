<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

test('bot token command stores hashed and plain token for the user', function () {
    $user = createCommandTestUser();

    $exitCode = Artisan::call('bot:token', [
        'user' => (string) $user->id,
    ]);

    expect($exitCode)->toBe(0);

    $user->refresh();

    expect($user->api_token)->not->toBeNull();
    expect($user->api_token_plain)->toBeString();
    expect($user->api_token_plain)->toStartWith('peck_');
    expect(hash('sha256', $user->api_token_plain))->toBe($user->api_token);
});

test('bot token command revoke option clears both token columns', function () {
    $user = createCommandTestUser([
        'api_token' => hash('sha256', 'peck_existing_token'),
        'api_token_plain' => 'peck_existing_token',
    ]);

    $exitCode = Artisan::call('bot:token', [
        'user' => $user->email,
        '--revoke' => true,
    ]);

    expect($exitCode)->toBe(0);

    $user->refresh();

    expect($user->api_token)->toBeNull();
    expect($user->api_token_plain)->toBeNull();
});

function createCommandTestUser(array $attributes = []): User
{
    $user = new User;
    $user->forceFill(array_merge([
        'name' => fake()->name(),
        'email' => fake()->unique()->safeEmail(),
        'password' => Hash::make('password'),
        'level' => 0,
    ], $attributes));

    $user->save();

    return $user;
}

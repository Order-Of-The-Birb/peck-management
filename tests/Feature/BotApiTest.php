<?php

use App\Models\PeckAlt;
use App\Models\PeckLeaveInfo;
use App\Models\PeckUser;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('bot api rejects requests without a valid token', function () {
    $response = $this->postJson('/api/bot/snapshot', []);

    $response->assertUnauthorized();
});

test('bot api accepts token in post body and returns snapshot data', function () {
    $plainToken = 'peck_test_token_123';
    createBotApiUser($plainToken);

    $owner = PeckUser::factory()->create([
        'gaijin_id' => 810001,
        'username' => 'owner-api-user',
        'status' => 'member',
    ]);
    $alt = PeckUser::factory()->create([
        'gaijin_id' => 810002,
        'username' => 'alt-api-user',
        'status' => 'alt',
    ]);

    PeckAlt::query()->create([
        'alt_id' => $alt->gaijin_id,
        'owner_id' => $owner->gaijin_id,
    ]);

    PeckLeaveInfo::query()->create([
        'user_id' => $alt->gaijin_id,
        'type' => PeckLeaveInfo::TYPE_LEFT_SQUADRON,
    ]);

    $response = $this->postJson('/api/bot/snapshot', [
        'token' => $plainToken,
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('peck_users.0.gaijin_id', 810001)
        ->assertJsonPath('peck_alts.0.alt_id', 810002)
        ->assertJsonPath('peck_leave_info.0.user_id', 810002);
});

test('bot api accepts bearer token', function () {
    $plainToken = 'peck_test_token_456';
    createBotApiUser($plainToken);

    PeckUser::factory()->create([
        'gaijin_id' => 820001,
        'username' => 'bearer-user',
        'status' => 'member',
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$plainToken,
    ])->postJson('/api/bot/peck-users', []);

    $response
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('data.0.username', 'bearer-user');
});

function createBotApiUser(string $plainToken): User
{
    $user = new User;
    $user->forceFill([
        'name' => fake()->name(),
        'email' => fake()->unique()->safeEmail(),
        'password' => Hash::make('password'),
        'level' => 0,
        'api_token' => hash('sha256', $plainToken),
    ]);

    $user->save();

    return $user;
}

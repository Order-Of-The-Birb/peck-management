<?php

use App\Models\ApiKey;
use App\Models\ThunderApiServerToken;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('peck.thunderapi_base_url', 'https://thunder.example');
    config()->set('peck.thunderapi_refresh.refresh_after_hours', 1);
    config()->set('peck.thunderapi_server.email', 'server@example.com');
    config()->set('peck.thunderapi_server.password', 'server-password');
});

function makeApiAdmin(): string
{
    $admin = User::query()->create([
        'name' => 'API Admin',
        'email' => 'api-admin@example.com',
        'password' => 'password',
    ]);

    $admin->forceFill([
        'email_verified_at' => now(),
        'level' => 1,
    ])->save();

    return ApiKey::issueForOwner($admin->id);
}

test('server token endpoint requires api key authentication', function () {
    $this->getJson('/api/v1/server-token')->assertUnauthorized();
});

test('server token endpoint is forbidden for unauthorized api key owners', function () {
    $reader = User::query()->create([
        'name' => 'Reader',
        'email' => 'reader@example.com',
        'password' => 'password',
    ]);

    $reader->forceFill([
        'email_verified_at' => now(),
        'level' => 0,
    ])->save();

    $apiToken = ApiKey::issueForOwner($reader->id);

    $this->getJson('/api/v1/server-token?token='.$apiToken)->assertForbidden();
});

test('server token endpoint returns the active server token', function () {
    $apiToken = makeApiAdmin();

    ThunderApiServerToken::factory()->create(['token' => 'server-token']);

    Http::fake();

    $this->getJson('/api/v1/server-token?token='.$apiToken)
        ->assertOk()
        ->assertJsonPath('token', 'server-token');

    Http::assertNothingSent();
});

test('server token endpoint logs in when no token exists', function () {
    $apiToken = makeApiAdmin();

    Http::fake([
        'https://thunder.example/v1/login' => Http::response([
            'status' => 'OK',
            'token' => 'fresh-server-token',
            'user_id' => 424242,
        ], 200),
    ]);

    $this->getJson('/api/v1/server-token?token='.$apiToken)
        ->assertOk()
        ->assertJsonPath('token', 'fresh-server-token');

    expect(ThunderApiServerToken::query()->value('token'))->toBe('fresh-server-token');
});

test('server token endpoint returns 503 when the server account is not configured', function () {
    config()->set('peck.thunderapi_server.email', null);
    config()->set('peck.thunderapi_server.password', null);

    $apiToken = makeApiAdmin();

    Http::fake();

    $this->getJson('/api/v1/server-token?token='.$apiToken)
        ->assertStatus(503)
        ->assertJsonPath('message', 'ThunderAPI server account is not configured.');

    Http::assertNothingSent();
});

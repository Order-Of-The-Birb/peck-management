<?php

use App\Actions\ServerThunderApi;
use App\Models\ThunderApiServerToken;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('peck.thunderapi_base_url', 'https://thunder.example');
    config()->set('peck.thunderapi_refresh.refresh_after_hours', 1);
});

test('server thunderapi token logs in and stores a token when none exists', function () {
    config()->set('peck.thunderapi_server.email', 'server@example.com');
    config()->set('peck.thunderapi_server.password', 'server-password');

    Http::fake([
        'https://thunder.example/v1/login' => Http::response([
            'status' => 'OK',
            'token' => 'server-token',
            'user_id' => 424242,
        ], 200),
    ]);

    expect(app(ServerThunderApi::class)->token())->toBe('server-token')
        ->and(ThunderApiServerToken::query()->count())->toBe(1)
        ->and(ThunderApiServerToken::query()->first()?->gaijin_id)->toBe(424242);
});

test('server thunderapi token reuses a fresh token without making requests', function () {
    config()->set('peck.thunderapi_server.email', 'server@example.com');
    config()->set('peck.thunderapi_server.password', 'server-password');

    ThunderApiServerToken::factory()->create(['token' => 'existing-token']);

    Http::fake();

    expect(app(ServerThunderApi::class)->token())->toBe('existing-token');

    Http::assertNothingSent();
});

test('server thunderapi token refreshes a due token', function () {
    config()->set('peck.thunderapi_server.email', 'server@example.com');
    config()->set('peck.thunderapi_server.password', 'server-password');

    $serverToken = ThunderApiServerToken::factory()->create([
        'token' => 'due-token',
        'refreshed_at' => now()->subHours(2),
    ]);

    $newExpiry = now()->addHour()->timestamp;

    Http::fake([
        'https://thunder.example/v1/refresh-token' => Http::response([
            'status' => 'OK',
            'expires' => $newExpiry,
        ], 200),
    ]);

    expect(app(ServerThunderApi::class)->token())->toBe('due-token');

    $serverToken->refresh();

    expect($serverToken->expires_at)->toBe($newExpiry);
});

test('server thunderapi token re-authenticates when the stored token is expired', function () {
    config()->set('peck.thunderapi_server.email', 'server@example.com');
    config()->set('peck.thunderapi_server.password', 'server-password');

    ThunderApiServerToken::factory()->expired()->create(['token' => 'expired-token']);

    Http::fake([
        'https://thunder.example/v1/login' => Http::response([
            'status' => 'OK',
            'token' => 'fresh-token',
            'user_id' => 424242,
        ], 200),
    ]);

    expect(app(ServerThunderApi::class)->token())->toBe('fresh-token')
        ->and(ThunderApiServerToken::query()->value('token'))->toBe('fresh-token');
});

test('server thunderapi token re-authenticates when refresh reports the token invalid', function () {
    config()->set('peck.thunderapi_server.email', 'server@example.com');
    config()->set('peck.thunderapi_server.password', 'server-password');

    ThunderApiServerToken::factory()->create([
        'token' => 'stale-token',
        'refreshed_at' => now()->subHours(2),
    ]);

    Http::fake([
        'https://thunder.example/v1/refresh-token' => Http::response([
            'status' => 'FAIL',
            'detail' => 'Invalid token',
        ], 404),
        'https://thunder.example/v1/login' => Http::response([
            'status' => 'OK',
            'token' => 'fresh-token',
            'user_id' => 424242,
        ], 200),
    ]);

    expect(app(ServerThunderApi::class)->token())->toBe('fresh-token')
        ->and(ThunderApiServerToken::query()->value('token'))->toBe('fresh-token');
});

test('server thunderapi token throws when credentials are missing', function () {
    config()->set('peck.thunderapi_server.email', null);
    config()->set('peck.thunderapi_server.password', null);

    expect(fn () => app(ServerThunderApi::class)->token())
        ->toThrow(RuntimeException::class, 'Missing ThunderAPI server account.');
});

test('refresh command keeps the server token active', function () {
    $serverToken = ThunderApiServerToken::factory()->create([
        'token' => 'server-token',
        'refreshed_at' => now()->subHours(2),
    ]);

    $newExpiry = now()->addHour()->timestamp;

    Http::fake([
        'https://thunder.example/v1/refresh-token' => Http::response([
            'status' => 'OK',
            'expires' => $newExpiry,
        ], 200),
    ]);

    $this->artisan('thunderapi:refresh-tokens')->assertSuccessful();

    $serverToken->refresh();

    expect($serverToken->expires_at)->toBe($newExpiry);
});

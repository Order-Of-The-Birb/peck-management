<?php

use App\Actions\ResolveUsernames;
use App\Actions\ThunderApi;
use App\Models\ThunderApiServerToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('peck.thunderapi_base_url', 'https://thunder.example');
    config()->set('peck.thunderapi_server.email', 'server@example.com');
    config()->set('peck.thunderapi_server.password', 'server-password');
});

test('thunderapi terse lookup returns nicknames keyed by gaijin id', function () {
    Http::fake([
        'https://thunder.example/v1/users/terse*' => Http::response([
            '800001' => ['nick' => 'Alpha'],
            '800002' => ['nick' => 'Beta'],
        ], 200),
    ]);

    $usernames = app(ThunderApi::class)->getUsersTerse('token', [800001, 800002]);

    expect($usernames)->toBe([
        800001 => 'Alpha',
        800002 => 'Beta',
    ]);

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), '/v1/users/terse')
            && str_contains($request->url(), 'id=800001')
            && str_contains($request->url(), 'id=800002');
    });
});

test('resolve usernames caches results and avoids repeat lookups', function () {
    ThunderApiServerToken::factory()->create(['token' => 'server-token']);

    Http::fake([
        'https://thunder.example/v1/users/terse*' => Http::response([
            '800001' => ['nick' => 'CachedUser'],
        ], 200),
    ]);

    $resolver = app(ResolveUsernames::class);

    expect($resolver->resolve([800001]))->toBe([800001 => 'CachedUser']);
    expect(Cache::get('peck:thunderapi:username:800001'))->toBe('CachedUser');

    Http::fake();

    expect($resolver->resolve([800001]))->toBe([800001 => 'CachedUser']);

    Http::assertNothingSent();
});

test('resolve usernames returns empty when thunderapi is not configured', function () {
    config()->set('peck.thunderapi_server.email', null);
    config()->set('peck.thunderapi_server.password', null);

    Http::fake();

    expect(app(ResolveUsernames::class)->resolve([800001]))->toBe([]);

    Http::assertNothingSent();
});

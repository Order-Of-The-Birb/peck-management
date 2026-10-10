<?php

use App\Jobs\NotifyDiscordBotCacheInvalidation;
use App\Models\ApiKey;
use App\Models\PeckUser;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

test('manual invalidate cache endpoint requires api key authentication', function () {
    $this->postJson('/api/invalidate-cache')
        ->assertUnauthorized();
});

test('manual invalidate cache endpoint requires an authorized api key owner', function () {
    Queue::fake();

    $token = issueApiTokenForUserWithLevel(0);

    $this->postJson('/api/invalidate-cache', [
        'token' => $token,
    ])->assertForbidden();
});

test('manual invalidate cache endpoint dispatches cache invalidation job', function () {
    Queue::fake();

    $token = issueApiTokenForUserWithLevel(1);

    $this->postJson('/api/invalidate-cache', [
        'token' => $token,
    ])->assertAccepted()
        ->assertJsonPath('status', 'queued');

    Queue::assertPushed(NotifyDiscordBotCacheInvalidation::class);
});

test('peck user observer dispatches cache invalidation job on create update and delete', function () {
    Queue::fake();

    $peckUser = PeckUser::factory()->create([
        'gaijin_id' => 920001,
    ]);

    $peckUser->update([
        'discord_id' => 111,
    ]);

    $peckUser->delete();

    Queue::assertPushedTimes(NotifyDiscordBotCacheInvalidation::class, 3);
});

test('cache invalidation job posts to the configured bot endpoint with bearer auth', function () {
    config()->set('services.discord_bot.invalidate_cache_url', 'http://127.0.0.1:5000/invalidate-cache');
    config()->set('services.discord_bot.shared_secret', 'shared-secret-value');

    Http::fake([
        'http://127.0.0.1:5000/invalidate-cache' => Http::response(),
    ]);

    (new NotifyDiscordBotCacheInvalidation)->handle();

    Http::assertSent(function ($request): bool {
        return $request->url() === 'http://127.0.0.1:5000/invalidate-cache'
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer shared-secret-value');
    });
});

test('cache invalidation job fails silently when the bot is unreachable', function () {
    config()->set('services.discord_bot.invalidate_cache_url', 'http://127.0.0.1:5000/invalidate-cache');
    config()->set('services.discord_bot.shared_secret', 'shared-secret-value');

    Http::fake(static function (): void {
        throw new ConnectionException('Bot offline');
    });

    expect(fn () => (new NotifyDiscordBotCacheInvalidation)->handle())
        ->not->toThrow(Throwable::class);
});

test('cache invalidation job skips the bot request when newer database activity exists', function () {
    config()->set('services.discord_bot.invalidate_cache_url', 'http://127.0.0.1:5000/invalidate-cache');
    config()->set('services.discord_bot.shared_secret', 'shared-secret-value');

    Http::fake([
        'http://127.0.0.1:5000/invalidate-cache' => Http::response(),
    ]);

    Cache::put(NotifyDiscordBotCacheInvalidation::CACHE_VERSION_KEY, 5);

    (new NotifyDiscordBotCacheInvalidation(3))->handle();

    Http::assertNothingSent();
});

test('cache invalidation job posts when no newer database activity exists', function () {
    config()->set('services.discord_bot.invalidate_cache_url', 'http://127.0.0.1:5000/invalidate-cache');
    config()->set('services.discord_bot.shared_secret', 'shared-secret-value');

    Http::fake([
        'http://127.0.0.1:5000/invalidate-cache' => Http::response(),
    ]);

    Cache::put(NotifyDiscordBotCacheInvalidation::CACHE_VERSION_KEY, 3);

    (new NotifyDiscordBotCacheInvalidation(3))->handle();

    Http::assertSent(fn ($request): bool => $request->url() === 'http://127.0.0.1:5000/invalidate-cache');
});

test('cache invalidation job is debounced with a delay and the current version', function () {
    Queue::fake();

    NotifyDiscordBotCacheInvalidation::dispatchDebounced();

    Queue::assertPushed(NotifyDiscordBotCacheInvalidation::class, function ($job): bool {
        return $job->delay === NotifyDiscordBotCacheInvalidation::IDLE_SECONDS
            && $job->version === Cache::get(NotifyDiscordBotCacheInvalidation::CACHE_VERSION_KEY);
    });
});

test('cache invalidation job handles legacy payloads without a version', function () {
    config()->set('services.discord_bot.invalidate_cache_url', 'http://127.0.0.1:5000/invalidate-cache');
    config()->set('services.discord_bot.shared_secret', 'shared-secret-value');

    Http::fake([
        'http://127.0.0.1:5000/invalidate-cache' => Http::response(),
    ]);

    $legacyJob = unserialize('O:'.strlen(NotifyDiscordBotCacheInvalidation::class).':"'.NotifyDiscordBotCacheInvalidation::class.'":0:{}');

    expect(fn () => $legacyJob->handle())
        ->not->toThrow(Throwable::class);

    Http::assertSent(fn ($request): bool => $request->url() === 'http://127.0.0.1:5000/invalidate-cache');
});

function issueApiTokenForUserWithLevel(int $level): string
{
    $user = User::query()->create([
        'name' => 'API Cache Invalidation User '.$level,
        'email' => 'api-cache-invalidation-'.$level.'@example.com',
        'password' => 'password',
    ]);

    $user->forceFill([
        'email_verified_at' => now(),
        'level' => $level,
    ])->save();

    return ApiKey::issueForOwner($user->id);
}

<?php

use App\Jobs\NotifyDiscordBotCacheInvalidation;
use App\Models\ApiKey;
use App\Models\PeckUser;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
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
        'username' => 'observer_create_target',
    ]);

    $peckUser->update([
        'username' => 'observer_update_target',
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

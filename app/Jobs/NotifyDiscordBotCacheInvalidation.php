<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class NotifyDiscordBotCacheInvalidation implements ShouldQueue
{
    use Queueable;

    public const IDLE_SECONDS = 2;

    public const CACHE_VERSION_KEY = 'peck:discord-cache-invalidation:version';

    public int $version = 0;

    public function __construct(int $version = 0)
    {
        $this->version = $version;
    }

    public static function dispatchDebounced(): void
    {
        $version = Cache::increment(self::CACHE_VERSION_KEY);

        static::dispatch($version)
            ->onConnection('database')
            ->delay(self::IDLE_SECONDS);
    }

    public function handle(): void
    {
        if ($this->hasMoreRecentActivity()) {
            return;
        }

        $invalidateCacheUrl = config('services.discord_bot.invalidate_cache_url');
        $sharedSecret = config('services.discord_bot.shared_secret');

        if (! is_string($invalidateCacheUrl) || $invalidateCacheUrl === '' || ! is_string($sharedSecret) || $sharedSecret === '') {
            return;
        }

        try {
            Http::acceptJson()
                ->withToken($sharedSecret)
                ->connectTimeout(1)
                ->timeout(3)
                ->post($invalidateCacheUrl);
        } catch (Throwable) {
        }
    }

    private function hasMoreRecentActivity(): bool
    {
        $latestVersion = Cache::get(self::CACHE_VERSION_KEY);

        return is_int($latestVersion) && $latestVersion > $this->version;
    }
}

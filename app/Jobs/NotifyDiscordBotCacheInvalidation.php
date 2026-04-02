<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Throwable;

class NotifyDiscordBotCacheInvalidation implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
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
}

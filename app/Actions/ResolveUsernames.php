<?php

namespace App\Actions;

use App\Models\ThunderApiToken;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ResolveUsernames
{
    public function __construct(
        private readonly ThunderApi $thunderApi,
        private readonly ServerThunderApi $serverThunderApi,
    ) {}

    /**
     * Resolve ThunderAPI nicknames for the given Gaijin IDs.
     *
     * Usernames are cached locally and any lookup failure silently falls back
     * to an empty result so callers can display the raw Gaijin ID instead.
     *
     * When a token is provided it is used directly. Otherwise the server
     * account (.env ThunderAPI login) is used first, falling back to the
     * authenticated user's own ThunderAPI token.
     *
     * @param  list<int>  $gaijinIds
     * @return array<int, string>
     */
    public function resolve(array $gaijinIds, ?string $token = null): array
    {
        $gaijinIds = array_values(array_unique(array_filter(
            array_map('intval', $gaijinIds),
            static fn (int $gaijinId): bool => $gaijinId > 0,
        )));

        if ($gaijinIds === []) {
            return [];
        }

        $resolved = [];
        $missing = [];

        foreach ($gaijinIds as $gaijinId) {
            $cached = Cache::get($this->cacheKey($gaijinId));

            if (is_string($cached) && $cached !== '') {
                $resolved[$gaijinId] = $cached;
            } else {
                $missing[] = $gaijinId;
            }
        }

        if ($missing === []) {
            return $resolved;
        }

        $token ??= $this->resolveToken();

        if ($token === null) {
            return $resolved;
        }

        try {
            $fetched = $this->thunderApi->getUsersTerse($token, $missing);
        } catch (Throwable) {
            return $resolved;
        }

        foreach ($fetched as $gaijinId => $nickname) {
            Cache::put($this->cacheKey($gaijinId), $nickname, now()->addHours(6));
            $resolved[$gaijinId] = $nickname;
        }

        return $resolved;
    }

    protected function resolveToken(): ?string
    {
        if ($this->serverThunderApi->isConfigured()) {
            try {
                return $this->serverThunderApi->token();
            } catch (Throwable) {
                // Fall back to the authenticated user's token below.
            }
        }

        $token = ThunderApiToken::query()->find(auth()->id());

        return $token instanceof ThunderApiToken && ! $token->isExpired() ? $token->token : null;
    }

    protected function cacheKey(int $gaijinId): string
    {
        return 'peck:thunderapi:username:'.$gaijinId;
    }
}

<?php

namespace App\Actions;

use Illuminate\Support\Facades\Cache;
use Throwable;

class ResolveUsernames
{
    public function __construct(
        private readonly ThunderApi $thunderApi,
    ) {}

    /**
     * Resolve ThunderAPI nicknames for the given Gaijin IDs.
     *
     * Usernames are cached locally and any lookup failure silently falls back
     * to an empty result so callers can display the raw Gaijin ID instead.
     * The terse endpoint is public, so no client token is required.
     *
     * The optional $unreachable flag is set to true when ThunderAPI could not
     * be reached while resolving the requested usernames.
     *
     * @param  list<int>  $gaijinIds
     * @return array<int, string>
     */
    public function resolve(array $gaijinIds, ?bool &$unreachable = null): array
    {
        $unreachable = false;

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

        try {
            $fetched = $this->thunderApi->getUsersTerse(null, $missing);
        } catch (ThunderApiUnreachableException) {
            $unreachable = true;

            return $resolved;
        } catch (Throwable) {
            return $resolved;
        }

        foreach ($fetched as $gaijinId => $nickname) {
            Cache::put($this->cacheKey($gaijinId), $nickname, now()->addHours(6));
            $resolved[$gaijinId] = $nickname;
        }

        return $resolved;
    }

    protected function cacheKey(int $gaijinId): string
    {
        return 'peck:thunderapi:username:'.$gaijinId;
    }
}

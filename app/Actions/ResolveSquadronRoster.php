<?php

namespace App\Actions;

use App\Models\ThunderApiServerToken;
use App\Models\ThunderApiToken;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ResolveSquadronRoster
{
    public function __construct(
        private readonly ThunderApi $thunderApi,
        private readonly ServerThunderApi $serverThunderApi,
    ) {}

    /**
     * Resolve the squadron roster (members and applicants) from ThunderAPI.
     *
     * The roster is cached so repeated status lookups do not hammer ThunderAPI.
     * When a token is provided it is used directly; otherwise the server-wide
     * login is used, falling back to the authenticated user's own token.
     *
     * @return array{resolved:bool,member_ids:list<int>,applicant_ids:list<int>}
     */
    public function resolve(?string $token = null, ?string $clanId = null, ?bool &$unreachable = null): array
    {
        $unreachable = false;

        $clanId ??= (string) config('peck.squadron_id');

        if ($clanId === '') {
            return $this->emptyRoster();
        }

        $cacheKey = 'peck:thunderapi:roster:'.$clanId;
        $cached = Cache::get($cacheKey);

        if (is_array($cached) && isset($cached['resolved'])) {
            return $cached;
        }

        try {
            $token ??= $this->resolveToken();
        } catch (ThunderApiUnreachableException) {
            $unreachable = true;

            return $this->emptyRoster();
        } catch (Throwable) {
            return $this->emptyRoster();
        }

        if ($token === null) {
            return $this->emptyRoster();
        }

        $clan = $this->fetchClan($token, $clanId, $unreachable);

        if ($clan === null) {
            return $this->emptyRoster();
        }

        $roster = [
            'resolved' => true,
            'member_ids' => $this->extractIds($clan['members'] ?? null),
            'applicant_ids' => $this->extractIds($clan['candidates'] ?? null),
        ];

        Cache::put($cacheKey, $roster, now()->addSeconds($this->cacheSeconds()));

        return $roster;
    }

    /**
     * Fetch the clan data, re-authenticating once when the token was rejected.
     *
     * @return array<string, mixed>|null
     */
    protected function fetchClan(string $token, string $clanId, bool &$unreachable): ?array
    {
        try {
            return $this->thunderApi->getClan($token, $clanId);
        } catch (ThunderApiUnauthorizedException) {
            $this->expireToken($token);

            try {
                $freshToken = $this->resolveToken();
            } catch (ThunderApiUnreachableException) {
                $unreachable = true;

                return null;
            } catch (Throwable) {
                return null;
            }

            if ($freshToken === null || $freshToken === $token) {
                return null;
            }

            try {
                return $this->thunderApi->getClan($freshToken, $clanId);
            } catch (ThunderApiUnreachableException) {
                $unreachable = true;

                return null;
            } catch (Throwable) {
                return null;
            }
        } catch (ThunderApiUnreachableException) {
            $unreachable = true;

            return null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Expire the stored copy of a rejected token so it gets re-authenticated.
     */
    protected function expireToken(string $token): void
    {
        ThunderApiServerToken::query()
            ->where('token', $token)
            ->update(['expires_at' => now()->subSecond()->timestamp]);

        ThunderApiToken::query()
            ->where('token', $token)
            ->update(['expires_at' => now()->subSecond()->timestamp]);
    }

    /**
     * @param  array{resolved:bool,member_ids:list<int>,applicant_ids:list<int>}  $roster
     */
    public function statusFor(int $gaijinId, array $roster): ?string
    {
        if (! ($roster['resolved'] ?? false)) {
            return null;
        }

        if (in_array($gaijinId, $roster['member_ids'], true)) {
            return 'member';
        }

        if (in_array($gaijinId, $roster['applicant_ids'], true)) {
            return 'applicant';
        }

        return 'ex_member';
    }

    /**
     * @return array{resolved:bool,member_ids:list<int>,applicant_ids:list<int>}
     */
    protected function emptyRoster(): array
    {
        return [
            'resolved' => false,
            'member_ids' => [],
            'applicant_ids' => [],
        ];
    }

    protected function resolveToken(): ?string
    {
        if ($this->serverThunderApi->isConfigured()) {
            try {
                return $this->serverThunderApi->token();
            } catch (ThunderApiUnreachableException) {
                throw new ThunderApiUnreachableException('Unable to reach ThunderAPI. Please try again later.');
            } catch (Throwable) {
                // Fall back to the authenticated user's token below.
            }
        }

        $token = ThunderApiToken::query()->find(auth()->id());

        return $token instanceof ThunderApiToken && ! $token->isExpired() ? $token->token : null;
    }

    protected function cacheSeconds(): int
    {
        return max(30, (int) config('peck.thunderapi_members.cache_seconds', 300));
    }

    /**
     * @return list<int>
     */
    protected function extractIds(mixed $entries): array
    {
        if (! is_array($entries)) {
            return [];
        }

        if (isset($entries['uid'])) {
            $uid = $entries['uid'];

            return is_numeric($uid) && (int) $uid > 0 ? [(int) $uid] : [];
        }

        return collect($entries)
            ->filter(fn (mixed $entry): bool => is_array($entry) && isset($entry['uid']))
            ->map(fn (array $entry): int => (int) $entry['uid'])
            ->filter(fn (int $uid): bool => $uid > 0)
            ->unique()
            ->values()
            ->all();
    }
}

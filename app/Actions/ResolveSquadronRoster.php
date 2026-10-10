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
     * @return array{resolved:bool,member_ids:list<int>,applicant_ids:list<int>,member_join_dates:array<int,int>,member_initiators:array<int,array{initiator:int,nickname:?string}>}
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
            'member_join_dates' => $this->extractJoinDates($clan['members'] ?? null),
            'member_initiators' => $this->extractInitiators($clan['members'] ?? null),
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
     * @param  array{resolved:bool,member_ids:list<int>,applicant_ids:list<int>,member_join_dates:array<int,int>,member_initiators:array<int,array{initiator:int,nickname:?string}>}  $roster
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
     * Return the join date (unix timestamp) for a member, if known.
     *
     * @param  array{resolved:bool,member_ids:list<int>,applicant_ids:list<int>,member_join_dates:array<int,int>,member_initiators:array<int,array{initiator:int,nickname:?string}>}  $roster
     */
    public function joinDateFor(int $gaijinId, array $roster): ?int
    {
        if (! ($roster['resolved'] ?? false)) {
            return null;
        }

        $joinDates = $roster['member_join_dates'] ?? [];

        return $joinDates[$gaijinId] ?? null;
    }

    /**
     * Return the initiator (recruiter) for a member, if known.
     *
     * @param  array{resolved:bool,member_ids:list<int>,applicant_ids:list<int>,member_join_dates:array<int,int>,member_initiators:array<int,array{initiator:int,nickname:?string}>}  $roster
     * @return array{initiator:int,nickname:?string}|null
     */
    public function initiatorFor(int $gaijinId, array $roster): ?array
    {
        if (! ($roster['resolved'] ?? false)) {
            return null;
        }

        $initiators = $roster['member_initiators'] ?? [];

        return $initiators[$gaijinId] ?? null;
    }

    /**
     * @return array{resolved:bool,member_ids:list<int>,applicant_ids:list<int>,member_join_dates:array<int,int>,member_initiators:array<int,array{initiator:int,nickname:?string}>}
     */
    protected function emptyRoster(): array
    {
        return [
            'resolved' => false,
            'member_ids' => [],
            'applicant_ids' => [],
            'member_join_dates' => [],
            'member_initiators' => [],
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

    /**
     * Extract member join dates (Gaijin ID => unix timestamp) from the clan payload.
     *
     * @return array<int, int>
     */
    protected function extractJoinDates(mixed $entries): array
    {
        if (! is_array($entries)) {
            return [];
        }

        if (isset($entries['uid'])) {
            $uid = $entries['uid'];
            $date = $entries['date'] ?? null;

            if (is_numeric($uid) && (int) $uid > 0 && is_numeric($date)) {
                return [(int) $uid => (int) $date];
            }

            return [];
        }

        $joinDates = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $uid = $entry['uid'] ?? null;
            $date = $entry['date'] ?? null;

            if (is_numeric($uid) && (int) $uid > 0 && is_numeric($date)) {
                $joinDates[(int) $uid] = (int) $date;
            }
        }

        return $joinDates;
    }

    /**
     * Extract member initiators (recruiters) from the clan payload.
     *
     * @return array<int, array{initiator:int, nickname:?string}>
     */
    protected function extractInitiators(mixed $entries): array
    {
        if (! is_array($entries)) {
            return [];
        }

        $memberEntries = isset($entries['uid']) ? [$entries] : $entries;

        $initiators = [];

        foreach ($memberEntries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $uid = $entry['uid'] ?? null;
            $initiator = $entry['initiator'] ?? null;
            $nickname = $entry['initiator_nick'] ?? null;

            if (! is_numeric($uid) || (int) $uid <= 0 || ! is_numeric($initiator) || (int) $initiator <= 0) {
                continue;
            }

            $initiators[(int) $uid] = [
                'initiator' => (int) $initiator,
                'nickname' => is_string($nickname) && $nickname !== '' ? $nickname : null,
            ];
        }

        return $initiators;
    }
}

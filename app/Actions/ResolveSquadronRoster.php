<?php

namespace App\Actions;

use Illuminate\Support\Facades\Cache;
use Throwable;

class ResolveSquadronRoster
{
    /**
     * Map raw Gaijin role indexes to their display names.
     *
     * @var array<int, string>
     */
    private const ROLE_NAMES = [
        1 => 'Commander',
        2 => 'Officer',
        3 => 'Private',
        4 => 'Unknown',
        5 => 'Deputy',
        6 => 'Sergeant',
    ];

    public function __construct(
        private readonly ThunderApi $thunderApi,
    ) {}

    /**
     * Resolve the squadron roster (members and applicants) from ThunderAPI.
     *
     * The roster is cached so repeated status lookups do not hammer ThunderAPI.
     * The clan endpoint is public, so no client token is required.
     *
     * Pass $fresh as true to bypass the cached roster and fetch a new copy.
     *
     * @return array{resolved:bool,member_ids:list<int>,applicant_ids:list<int>,member_join_dates:array<int,int>,member_initiators:array<int,array{initiator:int,nickname:?string}>,member_roles:array<int,string>}
     */
    public function resolve(?string $clanId = null, ?bool &$unreachable = null, bool $fresh = false): array
    {
        $unreachable = false;

        $clanId ??= (string) config('peck.squadron_id');

        if ($clanId === '') {
            return $this->emptyRoster();
        }

        $cacheKey = 'peck:thunderapi:roster:'.$clanId;

        if (! $fresh) {
            $cached = Cache::get($cacheKey);

            if (is_array($cached) && isset($cached['resolved'])) {
                return $cached;
            }
        }

        $clan = $this->fetchClan($clanId, $unreachable);

        if ($clan === null) {
            return $this->emptyRoster();
        }

        $roster = [
            'resolved' => true,
            'member_ids' => $this->extractIds($clan['members'] ?? null),
            'applicant_ids' => $this->extractIds($clan['applicants'] ?? null),
            'member_join_dates' => $this->extractJoinDates($clan['members'] ?? null),
            'member_initiators' => $this->extractInitiators($clan['members'] ?? null),
            'member_roles' => $this->extractMemberRoles($clan['members'] ?? null),
        ];

        Cache::put($cacheKey, $roster, now()->addSeconds($this->cacheSeconds()));

        return $roster;
    }

    /**
     * Fetch the clan data from the public clan endpoint.
     *
     * @return array<string, mixed>|null
     */
    protected function fetchClan(string $clanId, bool &$unreachable): ?array
    {
        try {
            return $this->thunderApi->getClan(null, $clanId);
        } catch (ThunderApiUnreachableException) {
            $unreachable = true;

            return null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array{resolved:bool,member_ids:list<int>,applicant_ids:list<int>,member_join_dates:array<int,int>,member_initiators:array<int,array{initiator:int,nickname:?string}>,member_roles:array<int,string>}  $roster
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
     * @param  array{resolved:bool,member_ids:list<int>,applicant_ids:list<int>,member_join_dates:array<int,int>,member_initiators:array<int,array{initiator:int,nickname:?string}>,member_roles:array<int,string>}  $roster
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
     * @param  array{resolved:bool,member_ids:list<int>,applicant_ids:list<int>,member_join_dates:array<int,int>,member_initiators:array<int,array{initiator:int,nickname:?string}>,member_roles:array<int,string>}  $roster
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
     * Return the squadron role for a member, if known.
     *
     * @param  array{resolved:bool,member_ids:list<int>,applicant_ids:list<int>,member_join_dates:array<int,int>,member_initiators:array<int,array{initiator:int,nickname:?string}>,member_roles:array<int,string>}  $roster
     */
    public function roleFor(int $gaijinId, array $roster): ?string
    {
        if (! ($roster['resolved'] ?? false)) {
            return null;
        }

        $roles = $roster['member_roles'] ?? [];

        return $roles[$gaijinId] ?? null;
    }

    /**
     * @return array{resolved:bool,member_ids:list<int>,applicant_ids:list<int>,member_join_dates:array<int,int>,member_initiators:array<int,array{initiator:int,nickname:?string}>,member_roles:array<int,string>}
     */
    protected function emptyRoster(): array
    {
        return [
            'resolved' => false,
            'member_ids' => [],
            'applicant_ids' => [],
            'member_join_dates' => [],
            'member_initiators' => [],
            'member_roles' => [],
        ];
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
            $joinedAt = $entries['joined_at'] ?? null;

            if (is_numeric($uid) && (int) $uid > 0 && is_numeric($joinedAt)) {
                return [(int) $uid => (int) $joinedAt];
            }

            return [];
        }

        $joinDates = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $uid = $entry['uid'] ?? null;
            $joinedAt = $entry['joined_at'] ?? null;

            if (is_numeric($uid) && (int) $uid > 0 && is_numeric($joinedAt)) {
                $joinDates[(int) $uid] = (int) $joinedAt;
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
            $initiator = $entry['accepted_by'] ?? null;
            $nickname = $entry['accepted_by_nick'] ?? null;

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

    /**
     * Extract member roles (Gaijin ID => role name) from the clan payload.
     *
     * @return array<int, string>
     */
    protected function extractMemberRoles(mixed $entries): array
    {
        if (! is_array($entries)) {
            return [];
        }

        $memberEntries = isset($entries['uid']) ? [$entries] : $entries;

        $roles = [];

        foreach ($memberEntries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $uid = $entry['uid'] ?? null;
            $rank = $entry['rank'] ?? null;

            if (! is_numeric($uid) || (int) $uid <= 0) {
                continue;
            }

            $roleValue = is_array($rank) ? ($rank['value'] ?? null) : $rank;

            if (! is_numeric($roleValue)) {
                continue;
            }

            $name = self::ROLE_NAMES[(int) $roleValue] ?? null;

            if ($name !== null) {
                $roles[(int) $uid] = $name;
            }
        }

        return $roles;
    }
}

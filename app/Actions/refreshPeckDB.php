<?php

namespace App\Actions;

use App\Models\PeckLeaveInfo;
use App\Models\PeckUser;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RefreshPeckDB
{
    /**
     * @return array{members_received:int,users_created:int,users_updated:int,initiators_updated:int,marked_ex_members:int,reactivated_members:int,leave_records_removed:int}
     */
    public function handle(
        ?string $squadronName = null,
        bool $synchronizeLeaveStates = true,
        bool $dryRun = false,
    ): array {
        $squadron = $squadronName ?? config('peck.squadron_name');

        if (! is_string($squadron) || trim($squadron) === '') {
            throw new RuntimeException('Missing squadron name. Set PECK_SQUADRON_NAME or pass --squadron.');
        }

        $members = $this->fetchSquadronMembers($squadron);

        $stats = [
            'members_received' => count($members),
            'users_created' => 0,
            'users_updated' => 0,
            'initiators_updated' => 0,
            'marked_ex_members' => 0,
            'reactivated_members' => 0,
            'leave_records_removed' => 0,
        ];

        if ($dryRun) {
            return $stats;
        }

        DB::transaction(function () use (&$stats, $members, $synchronizeLeaveStates): void {
            $memberIds = array_values(array_unique(array_map(
                static fn (array $member): int => $member['gaijin_id'],
                $members,
            )));

            $existingUsers = PeckUser::query()->whereIn('gaijin_id', $memberIds)->get()->keyBy('gaijin_id');

            foreach ($members as $member) {
                $peckUser = $existingUsers->get($member['gaijin_id']);

                if ($peckUser === null) {
                    $newUser = PeckUser::query()->create([
                        'gaijin_id' => $member['gaijin_id'],
                        'username' => $member['username'],
                        'status' => 'unverified',
                        'joindate' => $member['joindate'],
                        'initiator' => null,
                    ]);
                    $existingUsers->put($member['gaijin_id'], $newUser);

                    $stats['users_created']++;

                    continue;
                }

                $wasReactivated = false;

                if ($peckUser->status === 'ex_member') {
                    $peckUser->status = 'member';
                    $wasReactivated = true;
                }

                $hasUpdates = false;

                if ($peckUser->username !== $member['username']) {
                    $peckUser->username = $member['username'];
                    $hasUpdates = true;
                }

                if ($peckUser->joindate === null && $member['joindate'] !== null) {
                    $peckUser->joindate = $member['joindate'];
                    $hasUpdates = true;
                }

                if ($wasReactivated) {
                    $stats['reactivated_members']++;
                    $hasUpdates = true;
                }

                if ($hasUpdates) {
                    $peckUser->save();
                    $stats['users_updated']++;
                }
            }

            $initiatorIds = array_filter(array_column($members, 'initiator'));
            $existingInitiators = PeckUser::query()->whereIn('gaijin_id', $initiatorIds)->pluck('gaijin_id')->flip();

            foreach ($members as $member) {
                if ($member['initiator'] === null) {
                    continue;
                }

                $peckUser = $existingUsers->get($member['gaijin_id']);

                if ($peckUser === null || $peckUser->status === 'ex_member') {
                    continue;
                }

                if (! $existingInitiators->has($member['initiator']) || $peckUser->initiator === $member['initiator']) {
                    continue;
                }

                $peckUser->initiator = $member['initiator'];
                $peckUser->save();
                $stats['initiators_updated']++;
            }

            $stats['leave_records_removed'] = PeckLeaveInfo::query()
                ->whereIn('user_id', $memberIds)
                ->delete();

            if (! $synchronizeLeaveStates) {
                return;
            }

            $leftUsers = PeckUser::query()
                ->whereIn('status', ['member', 'unverified'])
                ->whereNotIn('gaijin_id', $memberIds)
                ->get();

            foreach ($leftUsers as $leftUser) {
                $leftUser->status = 'ex_member';
                $leftUser->save();
                $stats['marked_ex_members']++;

                PeckLeaveInfo::query()->updateOrCreate(
                    ['user_id' => $leftUser->gaijin_id],
                    ['type' => PeckLeaveInfo::TYPE_LEFT_SQUADRON],
                );
            }
        });

        return $stats;
    }

    /**
     * @return list<array{gaijin_id:int,username:string,joindate:?Carbon,initiator:?int}>
     */
    protected function fetchSquadronMembers(string $squadronName): array
    {
        $baseUrl = rtrim((string) config('peck.thunderinsights_base_url'), '/');

        $response = Http::acceptJson()
            ->timeout(20)
            ->retry(3, 500)
            ->get($baseUrl.'/clans/direct/clan/search/', [
                'clan' => $squadronName,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                sprintf('Failed to fetch squadron data (HTTP %d).', $response->status()),
            );
        }

        $members = Arr::get($response->json(), 'clan.members');

        if (! is_array($members)) {
            throw new RuntimeException('Malformed squadron response: clan.members is missing.');
        }

        $normalizedMembers = [];

        foreach ($members as $member) {
            if (! is_array($member)) {
                continue;
            }

            $gaijinId = (int) ($member['uid'] ?? 0);
            $username = $this->normalizeUsername((string) ($member['nick'] ?? ''));

            if ($gaijinId <= 0 || $username === '') {
                continue;
            }

            $joinTimestamp = Arr::get($member, 'date');
            $initiator = Arr::get($member, 'initiator');

            $normalizedMembers[] = [
                'gaijin_id' => $gaijinId,
                'username' => $username,
                'joindate' => is_numeric($joinTimestamp)
                    ? Carbon::createFromTimestampUTC((int) $joinTimestamp)
                    : null,
                'initiator' => is_numeric($initiator)
                    ? (int) $initiator
                    : null,
            ];
        }

        return $normalizedMembers;
    }

    protected function normalizeUsername(string $username): string
    {
        $trimmed = trim($username);

        if (str_contains($trimmed, '@')) {
            return explode('@', $trimmed, 2)[0];
        }

        return $trimmed;
    }
}

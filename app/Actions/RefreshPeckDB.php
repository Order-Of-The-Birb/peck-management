<?php

namespace App\Actions;

use App\Models\PeckUser;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RefreshPeckDB
{
    /**
     * @return array{members_received:int,users_created:int}
     */
    public function handle(
        ?string $squadronName = null,
        bool $dryRun = false,
    ): array {
        $squadron = $squadronName ?? config('peck.squadron_name');

        if (! is_string($squadron) || trim($squadron) === '') {
            throw new RuntimeException('Missing squadron name. Set SQUADRON_NAME or pass --squadron.');
        }

        $members = $this->fetchSquadronMembers($squadron);

        $stats = [
            'members_received' => count($members),
            'users_created' => 0,
        ];

        if ($dryRun) {
            return $stats;
        }

        $memberIds = array_values(array_unique(array_map(
            static fn (array $member): int => $member['gaijin_id'],
            $members,
        )));

        $existingGaijinIds = PeckUser::query()
            ->whereIn('gaijin_id', $memberIds)
            ->pluck('gaijin_id')
            ->map(fn (mixed $gaijinId): int => (int) $gaijinId)
            ->flip();

        foreach ($memberIds as $gaijinId) {
            if ($existingGaijinIds->has($gaijinId)) {
                continue;
            }

            PeckUser::query()->create(['gaijin_id' => $gaijinId]);
            $stats['users_created']++;
        }

        return $stats;
    }

    /**
     * @return list<array{gaijin_id:int}>
     */
    protected function fetchSquadronMembers(string $squadronName): array
    {
        $token = $this->authenticate();

        $clan = $this->findClan($token, $squadronName);

        $clanId = (int) ($clan['_id'] ?? 0);

        if ($clanId <= 0) {
            throw new RuntimeException('Squadron search response is missing a valid clan id.');
        }

        $members = $this->fetchClanMembers($token, $clanId);

        $normalizedMembers = [];

        foreach ($members as $member) {
            if (! is_array($member)) {
                continue;
            }

            $gaijinId = (int) ($member['uid'] ?? 0);

            if ($gaijinId <= 0) {
                continue;
            }

            $normalizedMembers[] = [
                'gaijin_id' => $gaijinId,
            ];
        }

        return $normalizedMembers;
    }

    protected function baseUrl(): string
    {
        $baseUrl = trim((string) config('peck.thunderapi_base_url'));

        if ($baseUrl === '') {
            throw new RuntimeException('Missing ThunderAPI base URL. Set THUNDERAPI_BASE_URL.');
        }

        return rtrim($baseUrl, '/');
    }

    protected function authenticate(): string
    {
        return app(ServerThunderApi::class)->token();
    }

    /**
     * @return array<string, mixed>
     */
    protected function findClan(string $token, string $squadronName): array
    {
        $response = Http::acceptJson()
            ->withToken($token)
            ->timeout(20)
            ->retry(3, 500, throw: false)
            ->get($this->baseUrl().'/v1/clans/search/', [
                'clanName' => $squadronName,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(sprintf('Failed to search for squadron (HTTP %d).', $response->status()));
        }

        $clans = $response->json();

        if (! is_array($clans)) {
            throw new RuntimeException('Malformed squadron search response.');
        }

        $normalizedName = strtolower(trim($squadronName));

        foreach ($clans as $clan) {
            if (! is_array($clan)) {
                continue;
            }

            $clanNameLower = (string) ($clan['namel'] ?? '');

            if ($clanNameLower === '' && isset($clan['name'])) {
                $clanNameLower = strtolower(trim((string) $clan['name']));
            }

            if ($clanNameLower === $normalizedName) {
                return $clan;
            }
        }

        throw new RuntimeException(sprintf('Squadron "%s" could not be found.', $squadronName));
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function fetchClanMembers(string $token, int $clanId): array
    {
        $response = Http::acceptJson()
            ->withToken($token)
            ->timeout(20)
            ->retry(3, 500, throw: false)
            ->get($this->baseUrl().'/v1/clans/'.$clanId);

        if (! $response->successful()) {
            throw new RuntimeException(sprintf('Failed to fetch squadron members (HTTP %d).', $response->status()));
        }

        $members = $response->json('members');

        if (! is_array($members)) {
            throw new RuntimeException('Malformed squadron response: members is missing.');
        }

        if ($members !== [] && ! array_is_list($members)) {
            return [$members];
        }

        return $members;
    }
}

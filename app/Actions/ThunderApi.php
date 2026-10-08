<?php

namespace App\Actions;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ThunderApi
{
    /**
     * Attempt a login against ThunderAPI and return the issued token and user ID.
     *
     * @return array{token:string,user_id:int}
     *
     * @throws ThunderApiTwoFactorRequiredException
     * @throws ThunderApiException
     */
    public function login(string $email, string $password): array
    {
        try {
            $response = Http::acceptJson()
                ->asForm()
                ->timeout(30)
                ->post($this->baseUrl().'/v1/login', [
                    'email' => $email,
                    'password' => $password,
                ]);
        } catch (ConnectionException) {
            $this->throwUnreachable();
        }

        if ($response->status() === 401 && $response->json('status') === '2STEP') {
            throw new ThunderApiTwoFactorRequiredException(
                types: array_values((array) $response->json('types')),
                requestId: $response->json('requestId'),
                userId: is_numeric($response->json('userId')) ? (int) $response->json('userId') : null,
            );
        }

        if ($response->status() === 408) {
            throw new ThunderApiTwoFactorRequiredException;
        }

        if ($response->status() === 429) {
            throw new ThunderApiException('ThunderAPI rate limit exceeded. Please wait a bit before trying again.');
        }

        if (! $response->successful()) {
            throw new ThunderApiException(sprintf('ThunderAPI login failed (HTTP %d).', $response->status()));
        }

        $token = $response->json('token');
        $userId = $response->json('user_id');

        if (! is_string($token) || $token === '') {
            throw new ThunderApiException('ThunderAPI login response is missing a token.');
        }

        if (! is_numeric($userId)) {
            throw new ThunderApiException('ThunderAPI login response is missing the user ID.');
        }

        return [
            'token' => $token,
            'user_id' => (int) $userId,
        ];
    }

    /**
     * Provide a two-factor code for a pending non-Gaijin Pass login.
     *
     * @throws ThunderApiException
     */
    public function answerTwoFactor(string $email, string $password, string $code): void
    {
        try {
            $response = Http::acceptJson()
                ->asForm()
                ->timeout(30)
                ->post($this->baseUrl().'/v1/answer-2fa', [
                    'email' => $email,
                    'password' => $password,
                    'code' => $code,
                ]);
        } catch (ConnectionException) {
            $this->throwUnreachable();
        }

        if ($response->status() === 429) {
            throw new ThunderApiException('ThunderAPI rate limit exceeded. Please wait a bit before trying again.');
        }

        if (! $response->successful()) {
            $detail = $response->json('detail');

            $message = is_string($detail) && $detail !== ''
                ? $detail
                : sprintf('ThunderAPI two-factor authentication failed (HTTP %d).', $response->status());

            throw new ThunderApiException($message);
        }
    }

    /**
     * Search squadrons by name and tag.
     *
     * @return list<array<string, mixed>>
     *
     * @throws ThunderApiException
     */
    public function searchClans(string $token, string $query): array
    {
        try {
            $response = Http::acceptJson()
                ->withToken($token)
                ->timeout(30)
                ->get($this->baseUrl().'/v1/clans/search/', [
                    'clanName' => $query,
                    'clanTag' => $query,
                ]);
        } catch (ConnectionException) {
            $this->throwUnreachable();
        }

        if ($response->status() === 401) {
            throw new ThunderApiUnauthorizedException('Your ThunderAPI token is no longer valid. Please reconnect your account.');
        }

        if ($response->status() === 429) {
            throw new ThunderApiException('ThunderAPI rate limit exceeded. Please wait a bit before trying again.');
        }

        if (! $response->successful()) {
            throw new ThunderApiException(sprintf('ThunderAPI squadron search failed (HTTP %d).', $response->status()));
        }

        $clans = $response->json();

        if (! is_array($clans)) {
            throw new ThunderApiException('Malformed squadron search response.');
        }

        return array_values(array_filter($clans, static fn (mixed $clan): bool => is_array($clan)));
    }

    /**
     * Refresh a token, returning its new expiry timestamp.
     *
     * Returns null when ThunderAPI reports the token is invalid or expired.
     *
     * @throws ThunderApiException
     */
    public function refreshToken(string $token): ?int
    {
        try {
            $response = Http::acceptJson()
                ->withToken($token)
                ->timeout(30)
                ->post($this->baseUrl().'/v1/refresh-token');
        } catch (ConnectionException) {
            $this->throwUnreachable();
        }

        if ($response->status() === 401 || $response->status() === 404) {
            return null;
        }

        if ($response->status() === 429) {
            throw new ThunderApiException('ThunderAPI rate limit exceeded while refreshing tokens.');
        }

        if (! $response->successful()) {
            throw new ThunderApiException(sprintf('ThunderAPI token refresh failed (HTTP %d).', $response->status()));
        }

        $expires = $response->json('expires');

        return is_numeric($expires) ? (int) $expires : null;
    }

    /**
     * Fetch a page of squadron logs.
     *
     * @return array{lastLog:string,logs:list<array<string,mixed>>}
     *
     * @throws ThunderApiException
     */
    public function getClanLogs(string $token, string $clanId, ?string $fromEntry = null, int $limit = 10): array
    {
        $query = ['limit' => $limit];

        if ($fromEntry !== null && $fromEntry !== '') {
            $query['fromEntry'] = $fromEntry;
        }

        try {
            $response = Http::acceptJson()
                ->withToken($token)
                ->timeout(30)
                ->get($this->baseUrl().'/v1/clans/logs/'.$clanId, $query);
        } catch (ConnectionException) {
            $this->throwUnreachable();
        }

        if ($response->status() === 401) {
            throw new ThunderApiUnauthorizedException('Your ThunderAPI token is no longer valid. Please reconnect your account.');
        }

        if ($response->status() === 429) {
            throw new ThunderApiException('ThunderAPI rate limit exceeded. Please wait a bit before trying again.');
        }

        if (! $response->successful()) {
            throw new ThunderApiException(sprintf('ThunderAPI squadron logs request failed (HTTP %d).', $response->status()));
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new ThunderApiException('Malformed squadron logs response.');
        }

        $lastLog = $data['lastLog'] ?? null;
        $logs = $data['logs'] ?? [];

        return [
            'lastLog' => is_string($lastLog) ? $lastLog : '',
            'logs' => array_values(array_filter($logs, static fn (mixed $entry): bool => is_array($entry))),
        ];
    }

    /**
     * Fetch the current clan applications for a squadron.
     *
     * @return list<array<string,mixed>>
     *
     * @throws ThunderApiException
     */
    public function getClanApplicants(string $token, string $clanId): array
    {
        try {
            $response = Http::acceptJson()
                ->withToken($token)
                ->timeout(30)
                ->get($this->baseUrl().'/v1/clans/applicants/'.$clanId);
        } catch (ConnectionException) {
            $this->throwUnreachable();
        }

        if ($response->status() === 401) {
            throw new ThunderApiUnauthorizedException('Your ThunderAPI token is no longer valid. Please reconnect your account.');
        }

        if ($response->status() === 429) {
            throw new ThunderApiException('ThunderAPI rate limit exceeded. Please wait a bit before trying again.');
        }

        if (! $response->successful()) {
            throw new ThunderApiException(sprintf('ThunderAPI applicants request failed (HTTP %d).', $response->status()));
        }

        $data = $response->json();

        if (! is_array($data)) {
            return [];
        }

        return array_values(array_filter($data, static fn (mixed $entry): bool => is_array($entry)));
    }

    /**
     * Fetch terse user info (nicknames) for the given Gaijin IDs.
     *
     * @param  list<int>  $gaijinIds
     * @return array<int, string> Gaijin ID => nickname
     *
     * @throws ThunderApiException
     */
    public function getUsersTerse(string $token, array $gaijinIds): array
    {
        $gaijinIds = array_values(array_unique(array_filter(
            array_map('intval', $gaijinIds),
            static fn (int $gaijinId): bool => $gaijinId > 0,
        )));

        if ($gaijinIds === []) {
            return [];
        }

        $nicknames = [];

        foreach (array_chunk($gaijinIds, 50) as $chunk) {
            $query = implode('&', array_map(static fn (int $gaijinId): string => 'id='.$gaijinId, $chunk));

            try {
                $response = Http::acceptJson()
                    ->withToken($token)
                    ->timeout(30)
                    ->get($this->baseUrl().'/v1/users/terse?'.$query);
            } catch (ConnectionException) {
                $this->throwUnreachable();
            }

            if ($response->status() === 401) {
                throw new ThunderApiUnauthorizedException('Your ThunderAPI token is no longer valid. Please reconnect your account.');
            }

            if ($response->status() === 429) {
                throw new ThunderApiException('ThunderAPI rate limit exceeded. Please wait a bit before trying again.');
            }

            if (! $response->successful()) {
                throw new ThunderApiException(sprintf('ThunderAPI users terse request failed (HTTP %d).', $response->status()));
            }

            $data = $response->json();

            if (! is_array($data)) {
                continue;
            }

            foreach ($data as $gaijinId => $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $nick = $entry['nick'] ?? null;

                if (is_string($nick) && $nick !== '') {
                    $nicknames[(int) $gaijinId] = $nick;
                }
            }
        }

        return $nicknames;
    }

    /**
     * Accept a clan application.
     *
     * @throws ThunderApiException
     */
    public function acceptApplicant(string $token, string $userId): void
    {
        try {
            $response = Http::acceptJson()
                ->withToken($token)
                ->timeout(30)
                ->post($this->baseUrl().'/v1/clans/accept/'.$userId);
        } catch (ConnectionException) {
            $this->throwUnreachable();
        }

        $this->throwApplicantActionFailure($response, 'accept');
    }

    /**
     * Reject a clan application.
     *
     * @throws ThunderApiException
     */
    public function rejectApplicant(string $token, string $userId, string $message = ''): void
    {
        try {
            $request = Http::acceptJson()
                ->withToken($token)
                ->timeout(30);

            if ($message !== '') {
                $request->withQueryParameters(['message' => $message]);
            }

            $response = $request->post($this->baseUrl().'/v1/clans/reject/'.$userId);
        } catch (ConnectionException) {
            $this->throwUnreachable();
        }

        $this->throwApplicantActionFailure($response, 'reject');
    }

    /**
     * @throws ThunderApiException
     */
    private function throwApplicantActionFailure(Response $response, string $action): void
    {
        if ($response->status() === 401) {
            throw new ThunderApiUnauthorizedException('Your ThunderAPI token is no longer valid. Please reconnect your account.');
        }

        if ($response->status() === 429) {
            throw new ThunderApiException('ThunderAPI rate limit exceeded. Please wait a bit before trying again.');
        }

        if ($response->successful()) {
            return;
        }

        $detail = $response->json('detail');

        throw new ThunderApiException(
            is_string($detail) && $detail !== ''
                ? $detail
                : sprintf('ThunderAPI %s applicant request failed (HTTP %d).', $action, $response->status())
        );
    }

    protected function throwUnreachable(): never
    {
        throw new ThunderApiException('Unable to reach ThunderAPI. Please try again later.');
    }

    protected function baseUrl(): string
    {
        $baseUrl = trim((string) config('peck.thunderapi_base_url'));

        if ($baseUrl === '') {
            throw new RuntimeException('Missing ThunderAPI base URL. Set THUNDERAPI_BASE_URL.');
        }

        return rtrim($baseUrl, '/');
    }
}

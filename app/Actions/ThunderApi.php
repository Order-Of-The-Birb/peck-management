<?php

namespace App\Actions;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ThunderApi
{
    /**
     * Attempt a login against ThunderAPI and return the issued token.
     *
     * @return array{token:string}
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

        if (! is_string($token) || $token === '') {
            throw new ThunderApiException('ThunderAPI login response is missing a token.');
        }

        return ['token' => $token];
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

        if ($response->status() === 404) {
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

<?php

namespace App\Actions;

use App\Models\ThunderApiServerToken;
use RuntimeException;

class ServerThunderApi
{
    public function __construct(private ThunderApi $thunderApi) {}

    /**
     * Resolve an active server token, refreshing or re-authenticating as needed.
     *
     * @throws ThunderApiException
     */
    public function token(): string
    {
        $serverToken = ThunderApiServerToken::query()->first();

        if ($serverToken === null || $serverToken->isExpired()) {
            return $this->authenticate();
        }

        $refreshAfterHours = max(1, (int) config('peck.thunderapi_refresh.refresh_after_hours'));

        if (! $serverToken->isRefreshDue($refreshAfterHours)) {
            return $serverToken->token;
        }

        try {
            $expires = $this->thunderApi->refreshToken($serverToken->token);
        } catch (ThunderApiException) {
            return $serverToken->token;
        }

        if ($expires === null) {
            return $this->authenticate();
        }

        $serverToken->forceFill([
            'expires_at' => $expires,
            'refreshed_at' => now(),
        ])->save();

        return $serverToken->token;
    }

    /**
     * Keep the stored server token fresh without returning it.
     *
     * @throws ThunderApiException
     */
    public function refresh(): void
    {
        $serverToken = ThunderApiServerToken::query()->first();

        if ($serverToken === null || $serverToken->isExpired()) {
            if (! $this->isConfigured()) {
                return;
            }

            $this->authenticate();

            return;
        }

        try {
            $expires = $this->thunderApi->refreshToken($serverToken->token);
        } catch (ThunderApiException) {
            return;
        }

        if ($expires === null) {
            if (! $this->isConfigured()) {
                return;
            }

            $this->authenticate();

            return;
        }

        $serverToken->forceFill([
            'expires_at' => $expires,
            'refreshed_at' => now(),
        ])->save();
    }

    public function isConfigured(): bool
    {
        return trim((string) config('peck.thunderapi_server.email', '')) !== ''
            && (string) config('peck.thunderapi_server.password', '') !== '';
    }

    /**
     * Authenticate the server account and persist its token.
     *
     * @throws ThunderApiException
     */
    protected function authenticate(): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Missing ThunderAPI server account. Set THUNDERAPI_EMAIL and THUNDERAPI_PASS.');
        }

        $result = $this->thunderApi->login(
            (string) config('peck.thunderapi_server.email'),
            (string) config('peck.thunderapi_server.password'),
        );

        ThunderApiServerToken::store($result['token'], $result['user_id']);

        return $result['token'];
    }
}

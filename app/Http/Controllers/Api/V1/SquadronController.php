<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ServerThunderApi;
use App\Actions\ThunderApi;
use App\Actions\ThunderApiException;
use App\Actions\ThunderApiUnauthorizedException;
use App\Actions\ThunderApiUnreachableException;
use App\Http\Controllers\Controller;
use App\Models\ThunderApiServerToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SquadronController extends Controller
{
    public function __construct(
        private readonly ThunderApi $thunderApi,
        private readonly ServerThunderApi $serverThunderApi,
    ) {}

    /**
     * Return the clan ID that PECK Management operates against.
     */
    public function clanId(): JsonResponse
    {
        $clanId = $this->squadronId();

        if ($clanId === null) {
            return $this->errorResponse(503, 'Squadron ID is not configured.');
        }

        return response()->json([
            'clan_id' => $clanId,
        ]);
    }

    /**
     * List the squadron's current membership applications.
     */
    public function applicants(Request $request): JsonResponse
    {
        $clanId = $this->squadronId();

        if ($clanId === null) {
            return $this->errorResponse(503, 'Squadron ID is not configured.');
        }

        if (! $this->serverThunderApi->isConfigured()) {
            return $this->errorResponse(503, 'ThunderAPI server account is not configured.');
        }

        try {
            $applicants = $this->thunderApiRequest(
                fn (string $token): array => $this->thunderApi->getClanApplicants($token, $clanId),
            );
        } catch (ThunderApiException $exception) {
            return $this->thunderApiErrorResponse($exception);
        }

        return response()->json([
            'data' => array_map(
                fn (array $applicant): array => $this->normalizeApplicant($applicant),
                $applicants,
            ),
        ]);
    }

    /**
     * List the squadron's activity log entries.
     */
    public function logs(Request $request): JsonResponse
    {
        $clanId = $this->squadronId();

        if ($clanId === null) {
            return $this->errorResponse(503, 'Squadron ID is not configured.');
        }

        if (! $this->serverThunderApi->isConfigured()) {
            return $this->errorResponse(503, 'ThunderAPI server account is not configured.');
        }

        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'between:1,50'],
            'from_entry' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $result = $this->thunderApiRequest(
                fn (string $token): array => $this->thunderApi->getClanLogs(
                    $token,
                    $clanId,
                    $validated['from_entry'] ?? null,
                    $validated['limit'] ?? 10,
                ),
            );
        } catch (ThunderApiException $exception) {
            return $this->thunderApiErrorResponse($exception);
        }

        return response()->json($result);
    }

    /**
     * Run a ThunderAPI request with the server-wide token, re-authenticating once on rejection.
     *
     * @param  callable(string): array<string, mixed>  $request
     * @return array<string, mixed>
     *
     * @throws ThunderApiException
     */
    private function thunderApiRequest(callable $request): array
    {
        $token = $this->serverThunderApi->token();

        try {
            return $request($token);
        } catch (ThunderApiUnauthorizedException) {
            ThunderApiServerToken::query()
                ->where('token', $token)
                ->update(['expires_at' => now()->subSecond()->timestamp]);

            return $request($this->serverThunderApi->token());
        }
    }

    /**
     * @param  array<string, mixed>  $applicant
     * @return array<string, mixed>
     */
    private function normalizeApplicant(array $applicant): array
    {
        $uid = $applicant['uid'] ?? null;
        $timestamp = $applicant['timestamp'] ?? null;
        $geodata = $applicant['geodata'] ?? null;
        $country = is_array($geodata) ? ($geodata['country'] ?? null) : null;
        $timezone = is_array($geodata) ? ($geodata['timezone'] ?? null) : null;

        return [
            'uid' => is_numeric($uid) ? (string) $uid : '',
            'nickname' => (string) ($applicant['nickname'] ?? ''),
            'timestamp' => is_numeric($timestamp) ? (int) $timestamp : null,
            'comment' => (string) ($applicant['comment'] ?? ''),
            'country' => is_string($country) && $country !== '' ? $country : null,
            'timezone' => is_int($timezone) ? $timezone : null,
        ];
    }

    private function squadronId(): ?string
    {
        $clanId = trim((string) config('peck.squadron_id'));

        return $clanId !== '' ? $clanId : null;
    }

    private function thunderApiErrorResponse(ThunderApiException $exception): JsonResponse
    {
        return $this->errorResponse(
            $exception instanceof ThunderApiUnreachableException ? 503 : 502,
            $exception->getMessage(),
        );
    }

    private function errorResponse(int $status, string $message): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }
}

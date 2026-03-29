<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PeckAlt;
use App\Models\PeckLeaveInfo;
use App\Models\PeckUser;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BotDataController extends Controller
{
    public function peckUsers(Request $request): JsonResponse
    {
        $authorizedUser = $this->authorizeRequest($request);

        if ($authorizedUser === null) {
            return response()->json([
                'message' => 'Invalid or missing token.',
            ], 401);
        }

        $peckUsers = PeckUser::query()
            ->with('initiatorUser:gaijin_id,username')
            ->orderBy('gaijin_id')
            ->get()
            ->map(function (PeckUser $peckUser): array {
                return [
                    'gaijin_id' => $peckUser->gaijin_id,
                    'username' => $peckUser->username,
                    'discord_id' => $peckUser->discord_id,
                    'tz' => $peckUser->tz,
                    'status' => $peckUser->status,
                    'joindate' => $peckUser->joindate?->toIso8601String(),
                    'initiator' => $peckUser->initiator,
                    'initiator_username' => $peckUser->initiatorUser?->username,
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'authorized_user_id' => $authorizedUser->id,
            'count' => count($peckUsers),
            'data' => $peckUsers,
        ]);
    }

    public function peckAlts(Request $request): JsonResponse
    {
        $authorizedUser = $this->authorizeRequest($request);

        if ($authorizedUser === null) {
            return response()->json([
                'message' => 'Invalid or missing token.',
            ], 401);
        }

        $peckAlts = PeckAlt::query()
            ->with([
                'altUser:gaijin_id,username',
                'ownerUser:gaijin_id,username',
            ])
            ->orderBy('alt_id')
            ->get()
            ->map(function (PeckAlt $peckAlt): array {
                return [
                    'alt_id' => $peckAlt->alt_id,
                    'owner_id' => $peckAlt->owner_id,
                    'alt_username' => $peckAlt->altUser?->username,
                    'owner_username' => $peckAlt->ownerUser?->username,
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'authorized_user_id' => $authorizedUser->id,
            'count' => count($peckAlts),
            'data' => $peckAlts,
        ]);
    }

    public function peckLeaveInfo(Request $request): JsonResponse
    {
        $authorizedUser = $this->authorizeRequest($request);

        if ($authorizedUser === null) {
            return response()->json([
                'message' => 'Invalid or missing token.',
            ], 401);
        }

        $leaveInfo = PeckLeaveInfo::query()
            ->with('user:gaijin_id,username')
            ->orderBy('user_id')
            ->get()
            ->map(function (PeckLeaveInfo $peckLeaveInfo): array {
                return [
                    'user_id' => $peckLeaveInfo->user_id,
                    'username' => $peckLeaveInfo->user?->username,
                    'type' => $peckLeaveInfo->type,
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'authorized_user_id' => $authorizedUser->id,
            'count' => count($leaveInfo),
            'data' => $leaveInfo,
        ]);
    }

    public function snapshot(Request $request): JsonResponse
    {
        $authorizedUser = $this->authorizeRequest($request);

        if ($authorizedUser === null) {
            return response()->json([
                'message' => 'Invalid or missing token.',
            ], 401);
        }

        return response()->json([
            'authorized_user_id' => $authorizedUser->id,
            'peck_users' => $this->peckUsers($request)->getData(true)['data'],
            'peck_alts' => $this->peckAlts($request)->getData(true)['data'],
            'peck_leave_info' => $this->peckLeaveInfo($request)->getData(true)['data'],
        ]);
    }

    protected function authorizeRequest(Request $request): ?User
    {
        $token = $request->bearerToken();

        if (! is_string($token) || $token === '') {
            $requestToken = $request->input('token');
            $token = is_string($requestToken) ? $requestToken : '';
        }

        if ($token === '') {
            return null;
        }

        $hashedToken = hash('sha256', $token);

        return User::query()
            ->where('api_token', $hashedToken)
            ->first();
    }
}

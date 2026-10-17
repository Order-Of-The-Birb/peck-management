<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ServerThunderApi;
use App\Actions\ThunderApiUnreachableException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Throwable;

class ServerTokenController extends Controller
{
    public function __construct(
        private readonly ServerThunderApi $serverThunderApi,
    ) {}

    public function __invoke(): JsonResponse
    {
        abort_unless(request()->user()?->canWrite() ?? false, 403);

        if (! $this->serverThunderApi->isConfigured()) {
            return response()->json([
                'message' => 'ThunderAPI server account is not configured.',
            ], 503);
        }

        try {
            $token = $this->serverThunderApi->token();
        } catch (ThunderApiUnreachableException) {
            return response()->json([
                'message' => 'Unable to reach ThunderAPI.',
            ], 503);
        } catch (Throwable) {
            return response()->json([
                'message' => 'Unable to resolve the ThunderAPI server token.',
            ], 502);
        }

        return response()->json([
            'token' => $token,
        ]);
    }
}

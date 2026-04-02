<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TriggerCacheInvalidationRequest;
use App\Jobs\NotifyDiscordBotCacheInvalidation;
use Illuminate\Http\JsonResponse;

class InvalidateCacheController extends Controller
{
    public function __invoke(TriggerCacheInvalidationRequest $request): JsonResponse
    {
        NotifyDiscordBotCacheInvalidation::dispatch()->onConnection('database');

        return response()->json([
            'status' => 'queued',
        ], 202);
    }
}

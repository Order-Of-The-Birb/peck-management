<?php

use App\Http\Controllers\Api\InvalidateCacheController;
use App\Http\Controllers\Api\V1\SquadronController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('api.key')->post('invalidate-cache', InvalidateCacheController::class);

Route::prefix('v1')->group(function (): void {
    Route::get('users', [UserController::class, 'index']);
    Route::get('users/{peckUser:gaijin_id}', [UserController::class, 'show']);
    Route::get('users/{peckUser:gaijin_id}/context', [UserController::class, 'showContexts']);

    Route::get('applicants', [SquadronController::class, 'applicants']);
    Route::get('logs', [SquadronController::class, 'logs']);
    Route::get('clan-id', [SquadronController::class, 'clanId']);

    Route::middleware('api.key')->group(function (): void {
        Route::post('users', [UserController::class, 'store']);
        Route::patch('users/{peckUser:gaijin_id}', [UserController::class, 'update']);
        Route::post('users/{peckUser:gaijin_id}/context', [UserController::class, 'storeContext']);
        Route::patch('users/{peckUser:gaijin_id}/context/{contextId}', [UserController::class, 'updateContext']);
        Route::delete('users/{peckUser:gaijin_id}/context/{contextId}', [UserController::class, 'destroyContext']);
    });
});

<?php

use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('users', [UserController::class, 'index']);
    Route::get('users/{peckUser:gaijin_id}', [UserController::class, 'show']);
    Route::get('users/{peckUser:gaijin_id}/leave_info', [UserController::class, 'showLeaveInfo']);

    Route::middleware('api.key')->group(function (): void {
        Route::post('users', [UserController::class, 'store']);
        Route::patch('users/{peckUser:gaijin_id}', [UserController::class, 'update']);
        Route::post('users/{peckUser:gaijin_id}/leave_info', [UserController::class, 'upsertLeaveInfo']);
        Route::patch('users/{peckUser:gaijin_id}/leave_info', [UserController::class, 'upsertLeaveInfo']);
    });
});

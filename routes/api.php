<?php

use App\Http\Controllers\Api\BotDataController;
use Illuminate\Support\Facades\Route;

Route::prefix('bot')->group(function (): void {
    Route::post('peck-users', [BotDataController::class, 'peckUsers']);
    Route::post('peck-alts', [BotDataController::class, 'peckAlts']);
    Route::post('peck-leave-info', [BotDataController::class, 'peckLeaveInfo']);
    Route::post('snapshot', [BotDataController::class, 'snapshot']);
});

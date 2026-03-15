<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('getuser', function (Request $request) {});
    Route::post('adduser', function (Request $request) {})->middleware('auth:sanctum');
});

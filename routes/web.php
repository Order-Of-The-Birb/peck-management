<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('dashboard');
})->middleware('auth')->name('dashboard');

Route::get('/members-missing-discord', function () {
    return view('dashboard', [
        'membersWithoutDiscordOnly' => true,
    ]);
})->middleware('auth')->name('dashboard.members-missing-discord');

Route::get('/unverified-users', function () {
    return view('dashboard', [
        'unverifiedOnly' => true,
    ]);
})->middleware('auth')->name('dashboard.unverified-users');

Route::get('/alt-accounts', function () {
    return view('alt-accounts');
})->middleware('auth')->name('dashboard.alt-accounts');

require __DIR__.'/settings.php';

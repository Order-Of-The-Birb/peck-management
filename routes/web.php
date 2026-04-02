<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
})->name('home');

Route::get('/dashboard', function () {
    return view('dashboard', [
        'dashboardSection' => 'users',
    ]);
})->middleware('auth')->name('dashboard');

Route::get('/leave-info', function () {
    return view('dashboard', [
        'dashboardSection' => 'leave_info',
    ]);
})->middleware('auth')->name('dashboard.leave-info');

Route::get('/alts', function () {
    return view('dashboard', [
        'dashboardSection' => 'alts',
    ]);
})->middleware('auth')->name('dashboard.alts');

require __DIR__.'/settings.php';

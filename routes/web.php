<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
})->name('home');

Route::get('/dashboard', function () {
    return view('dashboard', [
        'dashboardSection' => 'members',
    ]);
})->middleware(['auth'])->name('dashboard');

Route::redirect('/leave-info', '/dashboard')->middleware(['auth'])->name('dashboard.leave-info');

Route::redirect('/alts', '/dashboard')->middleware(['auth'])->name('dashboard.alts');

Route::redirect('/context', '/dashboard')->middleware(['auth'])->name('dashboard.context');

Route::get('/squadron/logs', function () {
    return view('dashboard', [
        'dashboardSection' => 'squadron_logs',
    ]);
})->middleware(['auth'])->name('dashboard.squadron.logs');

Route::get('/squadron/applications', function () {
    return view('dashboard', [
        'dashboardSection' => 'squadron_applications',
    ]);
})->middleware(['auth'])->name('dashboard.squadron.applications');

Route::get('/squadron/management', function () {
    return view('dashboard', [
        'dashboardSection' => 'squadron_management',
    ]);
})->middleware(['auth'])->name('dashboard.squadron.management');

require __DIR__.'/settings.php';

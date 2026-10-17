<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
})->name('home');

Route::get('/members', function () {
    return view('dashboard', [
        'dashboardSection' => 'members',
    ]);
})->middleware(['auth'])->name('dashboard');

Route::redirect('/leave-info', '/members')->middleware(['auth'])->name('dashboard.leave-info');

Route::redirect('/alts', '/members')->middleware(['auth'])->name('dashboard.alts');

Route::redirect('/context', '/members')->middleware(['auth'])->name('dashboard.context');

Route::get('/logs', function () {
    return view('dashboard', [
        'dashboardSection' => 'squadron_logs',
    ]);
})->middleware(['auth'])->name('dashboard.squadron.logs');

Route::get('/applicants', function () {
    return view('dashboard', [
        'dashboardSection' => 'squadron_applications',
    ]);
})->middleware(['auth'])->name('dashboard.squadron.applications');

require __DIR__.'/settings.php';

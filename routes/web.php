<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('CreativityInput');
})->name('home');

Route::get('/subtitle', function () {
    return Inertia::render('CreativitySubtitle');
})->name('subtitle');

Route::get('/subtitle/edit', function () {
    return Inertia::render('ChunkedVideoEditor');
})->name('subtitle.edit');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

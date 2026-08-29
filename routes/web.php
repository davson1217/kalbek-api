<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('cms.dashboard');
});

Route::prefix('cms')->name('cms.')->group(function (): void {
    Route::get('/', fn () => Inertia::render('Dashboard'))->name('dashboard');
});

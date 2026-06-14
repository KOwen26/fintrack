<?php

use App\Http\Controllers\LandingController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [LandingController::class, 'index'])->name('home');

require __DIR__ . '/auth.php';

Route::middleware(['auth', 'verified:auth.verification.notice'])->group(function () {
    Route::get('dashboard', fn () => Inertia::render('dashboard/dashboard'))->name('dashboard');

    require __DIR__ . '/settings.php';
});

require __DIR__ . '/dev.php';

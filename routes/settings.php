<?php

use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function (): void {
    Route::get('settings', fn () => Inertia::render('dashboard/settings/index'))->name('settings.index');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [ProfileController::class, 'editSecurity'])->name('security.edit');
    Route::put('settings/security', [ProfileController::class, 'updateSecurity'])->name('security.update');

    Route::get('settings/appearance', fn () => Inertia::render('dashboard/settings/appearance'))->name('appearance');

    // Theme
    Route::put('settings/theme', [ProfileController::class, 'updateTheme'])->name('settings.theme.update');
});

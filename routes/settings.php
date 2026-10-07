<?php

use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::controller(ProfileController::class)->group(function (): void {
        Route::get('settings', 'index')->name('settings.index');
        Route::get('settings/appearance', 'appearance')->name('appearance');

        Route::get('settings/profile', 'edit')->name('profile.edit');
        Route::patch('settings/profile', 'update')->name('profile.update');
        Route::delete('settings/profile', 'destroy')->name('profile.destroy');

        Route::get('settings/security', 'editSecurity')->name('security.edit');
        Route::put('settings/security', 'updateSecurity')->name('security.update');

        // Theme
        Route::put('settings/theme', 'updateTheme')->name('settings.theme.update');
    });
});

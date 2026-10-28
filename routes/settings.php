<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\TransactionTemplateController;
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

    Route::prefix('settings/transaction-templates')->name('transaction-templates.')->group(function (): void {
        Route::controller(TransactionTemplateController::class)->group(function (): void {
            Route::get('', 'index')->name('index');
            Route::get('create', 'create')->name('create');
            Route::post('', 'store')->name('store');
            Route::get('{template}', 'show')->name('show');
            Route::get('{template}/edit', 'edit')->name('edit');
            Route::put('{template}', 'update')->name('update');
            Route::delete('{template}', 'destroy')->name('destroy');
        });
    });
});

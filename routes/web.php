<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\StatisticController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransferController;
use Illuminate\Support\Facades\Route;

require __DIR__ . '/auth.php';

Route::get('/', fn () => to_route('auth.login'));

Route::middleware(['auth', 'verified:auth.verification.notice'])->group(function (): void {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Categories
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');

    // Accounts
    Route::prefix('accounts')->name('accounts.')->group(function (): void {
        Route::controller(AccountController::class)->group(function (): void {
            Route::get('', 'index')->name('index');
            Route::get('create', 'create')->name('create');
            Route::post('', 'store')->name('store');
            Route::get('{account}', 'show')->name('show');
            Route::get('{account}/edit', 'edit')->name('edit');
            Route::put('{account}', 'update')->name('update');
            Route::post('{account}/archive', 'archive')->name('archive');
            Route::post('{account}/restore', 'restore')->name('restore');
            Route::delete('{account}', 'destroy')->name('destroy');
        });
    });

    // Transactions
    Route::prefix('transactions')->name('transactions.')->group(function (): void {
        Route::controller(TransactionController::class)->group(function (): void {
            Route::get('', 'index')->name('index');
            Route::post('', 'store')->name('store');
            Route::get('create', 'create')->name('create');
            Route::get('{transaction}', 'show')->name('show');
            Route::get('{transaction}/edit', 'edit')->name('edit');
            Route::put('{transaction}', 'update')->name('update');
            Route::delete('{transaction}', 'destroy')->name('destroy');
        });
    });

    // Transfers (unit write surface — listing/show/delete stay on transactions)
    Route::prefix('transfers')->name('transfers.')->group(function (): void {
        Route::controller(TransferController::class)->group(function (): void {
            Route::post('', 'store')->name('store');
            Route::get('{transfer}/edit', 'edit')->name('edit');
            Route::put('{transfer}', 'update')->name('update');
        });
    });

    Route::prefix('statistics')->name('statistics.')->group(function (): void {
        Route::controller(StatisticController::class)->group(function (): void {
            Route::get('', 'index')->name('index');
        });
    });

    require __DIR__ . '/settings.php';
});

require __DIR__ . '/dev.php';

<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserThemeController;
use Illuminate\Support\Facades\Route;

require __DIR__ . '/auth.php';

Route::get('/', fn () => to_route('auth.login'));
// Route::get('/', [LandingController::class, 'index'])->name('home');

Route::middleware(['auth', 'verified:auth.verification.notice'])->group(function (): void {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Accounts
    Route::get('accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::get('accounts/create', [AccountController::class, 'create'])->name('accounts.create');
    Route::post('accounts', [AccountController::class, 'store'])->name('accounts.store');
    Route::get('accounts/{account}', [AccountController::class, 'show'])->name('accounts.show');
    Route::get('accounts/{account}/edit', [AccountController::class, 'edit'])->name('accounts.edit');
    Route::put('accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');
    Route::delete('accounts/{account}', [AccountController::class, 'destroy'])->name('accounts.destroy');
    Route::post('accounts/{account}/archive', [AccountController::class, 'archive'])->name('accounts.archive');
    Route::post('accounts/{account}/restore', [AccountController::class, 'restore'])->name('accounts.restore');

    // Categories
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');

    // Theme
    Route::put('settings/theme', [UserThemeController::class, 'update'])->name('settings.theme.update');

    // Transactions
    Route::prefix('transactions')->name('transactions.')->group(function (): void {
        Route::get('', [TransactionController::class, 'index'])->name('index');
        Route::get('create', [TransactionController::class, 'create'])->name('create');
        Route::get('{transaction}', [TransactionController::class, 'show'])->name('show');
        Route::post('', [TransactionController::class, 'store'])->name('store');
        Route::put('{transaction}', [TransactionController::class, 'update'])->name('update');
        Route::delete('{transaction}', [TransactionController::class, 'destroy'])->name('destroy');
    });

    // Reports (read-only — all GET)
    Route::get('accounts/{account}/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('accounts/{account}/reports/trend', [ReportController::class, 'trend'])->name('reports.trend');
    Route::get('accounts/{account}/reports/category-leak', [ReportController::class, 'categoryLeak'])->name('reports.category-leak');
    Route::get('accounts/{account}/reports/contribution-split', [ReportController::class, 'contributionSplit'])->name('reports.contribution-split');
    Route::get('accounts/{account}/reports/fixed-vs-variable', [ReportController::class, 'fixedVsVariable'])->name('reports.fixed-vs-variable');

    require __DIR__ . '/settings.php';
});

require __DIR__ . '/dev.php';

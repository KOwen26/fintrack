<?php

use App\Http\Controllers\AccountsController;
use App\Http\Controllers\BudgetsController;
use App\Http\Controllers\CategoriesController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\RecurringPresetsController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\TransactionPresetsController;
use App\Http\Controllers\TransactionsController;
use App\Http\Controllers\UserThemeController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

require __DIR__ . '/auth.php';

Route::get('/', fn () => to_route('auth.login'));
// Route::get('/', [LandingController::class, 'index'])->name('home');

Route::middleware(['auth', 'verified:auth.verification.notice'])->group(function (): void {
    Route::get('dashboard', fn () => Inertia::render('dashboard/dashboard'))->name('dashboard');

    // Accounts
    Route::get('accounts', [AccountsController::class, 'index'])->name('accounts.index');
    Route::get('accounts/create', [AccountsController::class, 'create'])->name('accounts.create');
    Route::post('accounts', [AccountsController::class, 'store'])->name('accounts.store');
    Route::get('accounts/{account}', [AccountsController::class, 'show'])->name('accounts.show');
    Route::get('accounts/{account}/edit', [AccountsController::class, 'edit'])->name('accounts.edit');
    Route::put('accounts/{account}', [AccountsController::class, 'update'])->name('accounts.update');
    Route::delete('accounts/{account}', [AccountsController::class, 'destroy'])->name('accounts.destroy');
    Route::post('accounts/{account}/archive', [AccountsController::class, 'archive'])->name('accounts.archive');
    Route::post('accounts/{account}/restore', [AccountsController::class, 'restore'])->name('accounts.restore');

    // Transaction Presets (templates)
    Route::get('transaction-presets', [TransactionPresetsController::class, 'index'])->name('transaction-presets.index');
    Route::post('transaction-presets', [TransactionPresetsController::class, 'store'])->name('transaction-presets.store');
    Route::put('transaction-presets/{preset}', [TransactionPresetsController::class, 'update'])->name('transaction-presets.update');
    Route::delete('transaction-presets/{preset}', [TransactionPresetsController::class, 'destroy'])->name('transaction-presets.destroy');

    // Recurring Presets
    Route::get('recurring-presets', [RecurringPresetsController::class, 'index'])->name('recurring-presets.index');
    Route::post('recurring-presets', [RecurringPresetsController::class, 'store'])->name('recurring-presets.store');
    Route::put('recurring-presets/{preset}', [RecurringPresetsController::class, 'update'])->name('recurring-presets.update');
    Route::delete('recurring-presets/{preset}', [RecurringPresetsController::class, 'destroy'])->name('recurring-presets.destroy');
    Route::post('recurring-presets/{preset}/toggle', [RecurringPresetsController::class, 'toggle'])->name('recurring-presets.toggle');

    // Categories
    Route::get('categories', [CategoriesController::class, 'index'])->name('categories.index');
    Route::post('categories', [CategoriesController::class, 'store'])->name('categories.store');
    Route::put('categories/{category}', [CategoriesController::class, 'update'])->name('categories.update');
    Route::delete('categories/{category}', [CategoriesController::class, 'destroy'])->name('categories.destroy');

    // Theme
    Route::put('settings/theme', [UserThemeController::class, 'update'])->name('settings.theme.update');

    // Transactions
    Route::get('accounts/{account}/transactions', [TransactionsController::class, 'index'])->name('transactions.index');
    Route::get('accounts/{account}/transactions/create', [TransactionsController::class, 'create'])->name('transactions.create');
    Route::post('accounts/{account}/transactions', [TransactionsController::class, 'store'])->name('transactions.store');
    Route::get('accounts/{account}/transactions/{transaction}/edit', [TransactionsController::class, 'edit'])->name('transactions.edit');
    Route::put('accounts/{account}/transactions/{transaction}', [TransactionsController::class, 'update'])->name('transactions.update');
    Route::delete('accounts/{account}/transactions/{transaction}', [TransactionsController::class, 'destroy'])->name('transactions.destroy');

    // Budgets
    Route::get('accounts/{account}/budgets', [BudgetsController::class, 'index'])->name('budgets.index');
    Route::post('accounts/{account}/budgets', [BudgetsController::class, 'store'])->name('budgets.store');
    Route::put('accounts/{account}/budgets/{budget}', [BudgetsController::class, 'update'])->name('budgets.update');
    Route::delete('accounts/{account}/budgets/{budget}', [BudgetsController::class, 'destroy'])->name('budgets.destroy');

    // Reports (read-only — all GET)
    Route::get('accounts/{account}/reports', [ReportsController::class, 'index'])->name('reports.index');
    Route::get('accounts/{account}/reports/trend', [ReportsController::class, 'trend'])->name('reports.trend');
    Route::get('accounts/{account}/reports/category-leak', [ReportsController::class, 'categoryLeak'])->name('reports.category-leak');
    Route::get('accounts/{account}/reports/contribution-split', [ReportsController::class, 'contributionSplit'])->name('reports.contribution-split');
    Route::get('accounts/{account}/reports/credit-utilization', [ReportsController::class, 'creditUtilization'])->name('reports.credit-utilization');
    Route::get('accounts/{account}/reports/fixed-vs-variable', [ReportsController::class, 'fixedVsVariable'])->name('reports.fixed-vs-variable');

    require __DIR__ . '/settings.php';
});

require __DIR__ . '/dev.php';

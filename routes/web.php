<?php

use App\Http\Controllers\AccountsController;
use App\Http\Controllers\CategoriesController;
use App\Http\Controllers\HouseholdInvitationsController;
use App\Http\Controllers\HouseholdsController;
use App\Http\Controllers\LandingController;
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

    // Categories
    Route::get('categories', [CategoriesController::class, 'index'])->name('categories.index');
    Route::post('categories', [CategoriesController::class, 'store'])->name('categories.store');
    Route::put('categories/{category}', [CategoriesController::class, 'update'])->name('categories.update');
    Route::delete('categories/{category}', [CategoriesController::class, 'destroy'])->name('categories.destroy');

    // Household
    Route::get('household/settings', [HouseholdsController::class, 'show'])->name('household.settings');
    Route::post('household', [HouseholdsController::class, 'store'])->name('household.store');
    Route::post('household/invite', [HouseholdsController::class, 'invite'])->name('household.invite');
    Route::delete('household/members/{member}', [HouseholdsController::class, 'removeMember'])->name('household.members.destroy');

    // Household invitations
    Route::get('household/invitations/{token}', [HouseholdInvitationsController::class, 'show'])->name('household.invitations.show');
    Route::post('household/invitations/{token}/accept', [HouseholdInvitationsController::class, 'accept'])->name('household.invitations.accept');
    Route::post('household/invitations/{token}/decline', [HouseholdInvitationsController::class, 'decline'])->name('household.invitations.decline');

    // Theme
    Route::put('settings/theme', [UserThemeController::class, 'update'])->name('settings.theme.update');

    require __DIR__ . '/settings.php';
});

require __DIR__ . '/dev.php';

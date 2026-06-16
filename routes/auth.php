<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\ConfirmablePasswordController;
use Laravel\Fortify\Http\Controllers\EmailVerificationNotificationController;
use Laravel\Fortify\Http\Controllers\EmailVerificationPromptController;
use Laravel\Fortify\Http\Controllers\NewPasswordController;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController;
use Laravel\Fortify\Http\Controllers\RegisteredUserController;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\VerifyEmailController;

Route::name('auth.')->group(function (): void {
    // Login
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->middleware('guest:' . config('fortify.guard'))
        ->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('guest:' . config('fortify.guard'));

    // Logout
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    // Registration
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->middleware('guest:' . config('fortify.guard'))
        ->name('register');
    Route::post('register', [RegisteredUserController::class, 'store'])
        ->middleware('guest:' . config('fortify.guard'));

    // Password Reset
    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->middleware('guest:' . config('fortify.guard'))
        ->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('guest:' . config('fortify.guard'))
        ->name('password.email');
    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->middleware('guest:' . config('fortify.guard'))
        ->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->middleware('guest:' . config('fortify.guard'))
        ->name('password.store');

    // Email Verification
    Route::get('email/verify', [EmailVerificationPromptController::class, '__invoke'])
        ->middleware('auth:' . config('fortify.guard'))
        ->name('verification.notice');
    Route::get('email/verify/{id}/{hash}', [VerifyEmailController::class, '__invoke'])
        ->middleware(['auth:' . config('fortify.guard'), 'signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware(['auth:' . config('fortify.guard'), 'throttle:6,1'])
        ->name('verification.send');

    // Confirm Password
    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->middleware('auth:' . config('fortify.guard'))
        ->name('password.confirm');
    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store'])
        ->middleware('auth:' . config('fortify.guard'));

    // Two-Factor Authentication (routes prepared; feature disabled in config)
    Route::get('two-factor-challenge', [TwoFactorAuthenticatedSessionController::class, 'create'])
        ->middleware('guest:' . config('fortify.guard'))
        ->name('two-factor.login');
    Route::post('two-factor-challenge', [TwoFactorAuthenticatedSessionController::class, 'store'])
        ->middleware('guest:' . config('fortify.guard'));
});

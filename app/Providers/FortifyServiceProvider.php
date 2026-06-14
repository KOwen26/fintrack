<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\PasswordResetResponse as PasswordResetResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PasswordResetResponseContract::class, function ($app, $params) {
            $status = $params['status'] ?? '';

            return new class($status) implements PasswordResetResponseContract
            {
                public function __construct(private readonly string $status) {}

                public function toResponse($request): Response
                {
                    return $request->wantsJson()
                        ? new JsonResponse(['message' => trans($this->status)], 200)
                        : redirect()->route('auth.login')->with('status', trans($this->status));
                }
            };
        });
    }

    public function boot(): void
    {
        Fortify::ignoreRoutes(); // Routes are defined in routes/auth.php with auth. prefix

        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(
                Str::transliterate(Str::lower($request->string('email')) . '|' . $request->ip())
            );
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        // Inertia view bindings — called by Fortify's controllers regardless of route definition
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => Route::has('auth.password.request'),
            'status' => session('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/register'));

        Fortify::requestPasswordResetLinkView(fn () => Inertia::render('auth/forgot-password', [
            'status' => session('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'user' => ['email' => $request->email, 'token' => $request->route('token')],
        ]));

        Fortify::verifyEmailView(fn () => Inertia::render('auth/verify-email', [
            'status' => session('status'),
        ]));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));
    }
}

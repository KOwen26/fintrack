<?php

namespace App\Providers;

use App\Events\RecurringPresetExecuted;
use App\Events\TransactionDeleted;
use App\Events\TransactionSaved;
use App\Listeners\InvalidateAccountBalanceCache;
use App\Listeners\InvalidateAccountReportCache;
use Exception;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

/**
 * @method RedirectResponse flash(string $message, string $type = 'success', array|null $meta = [])
 * @method \Illuminate\Http\Route getPreviousName()
 * @method Inertia mergeWithShared(string $key, $value)
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RedirectResponse::macro('flash', function (string $message, string $type = 'success', ?array $meta = []) {
            Inertia::flash([
                'type' => $type,
                'message' => $message,
                ...$meta,
            ]);

            return $this;
        });

        Route::macro('getPreviousName', function () {
            // Get the previous URL
            $previousUrl = URL::previous();

            // Create a request instance from that URL
            $request = Request::create($previousUrl);

            // Try to match the request to a route
            try {
                return resolve('router')->getRoutes()->match($request)->getName();
            } catch (Exception) {
                // Return null or a fallback if the previous URL
                // doesn't match a route in your app (e.g. it was an external link)
                return null;
            }
        });

        // Use auth.verification.verify since all auth routes are under the auth. name prefix
        VerifyEmail::createUrlUsing(fn ($notifiable) => URL::temporarySignedRoute(
            'auth.verification.verify',
            Date::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            ['id' => $notifiable->getKey(), 'hash' => sha1((string) $notifiable->getEmailForVerification())]
        ));

        Inertia::macro('mergeWithShared', function ($key, $value) {
            $shared = Inertia::getShared($key, []);

            // Merge with the new items
            return array_merge($shared, $value);
        });

        Event::listen(TransactionSaved::class, InvalidateAccountBalanceCache::class);
        Event::listen(TransactionDeleted::class, InvalidateAccountBalanceCache::class);
        Event::listen(RecurringPresetExecuted::class, [InvalidateAccountBalanceCache::class, 'handleRecurringPresetExecuted']);
        Event::listen(TransactionSaved::class, InvalidateAccountReportCache::class);
        Event::listen(TransactionDeleted::class, InvalidateAccountReportCache::class);
    }
}

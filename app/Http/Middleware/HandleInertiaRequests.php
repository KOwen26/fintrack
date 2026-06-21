<?php

namespace App\Http\Middleware;

use Illuminate\Support\Collection;
use App\Services\AccountService;
use App\Services\CategoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $method = $request->method();
        $authed = (bool) $request->user();

        return array_merge(parent::share($request), [
            'csrf_token' => csrf_token(),
            'auth' => [
                'user' => fn () => $authed
                    ? $request->user()->only('id', 'name', 'email', 'theme_preference')
                    : null,
                'permissions' => fn () => $authed
                    ? ($request->user()->getPermissionsViaRoles()->pluck('name')->toArray() ?? [])
                    : null,
            ],
            'flash' => [
                'type' => fn () => $request->session()->get('type'),
                'message' => fn () => $request->session()->get('message'),
                'details' => fn () => $request->session()->get('details'),
            ],
            'meta' => [
                'app_name' => config('app.name'),
                'current_route_name' => fn () => $method === 'GET' ? $request->route()->getName() : null,
                'previous_route_name' => fn () => $method === 'GET' ? Route::getPreviousName() : null,
            ],
            'static' => [
                'accounts' => fn (): Collection => $authed ? AccountService::getAccountsByUser($request->user()) : collect(),
                'categories' => fn (): Collection => $authed ? CategoryService::getCategories() : collect(),
                'groupedCategories' => fn (): Collection => $authed ? CategoryService::getGroupedCategories() : collect(),
            ],
        ]);
    }
}

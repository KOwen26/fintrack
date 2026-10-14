---
paths:
  - 'app/Http/Controllers/**'
---

# Controllers

## Controllers: singular, multi-method, explicit verb routes

Default to singular-named, multi-method resource-style controllers (`UserController`,
not `UsersController`) with explicit verb routes registered per action — this app does
not use `Route::resource` or invokable controllers. Custom actions (e.g. `archive`,
`restore`, report endpoints) are extra verb routes on the same controller, named
`resource.action`. This is the house default, not a hard rule — deviate when context
warrants.

## Implicit model binding only

Type-hint models in controller action signatures for route params; never use `Route::bind`
or manual `findOrFail` for the primary route model. `findOrFail` is only for secondary
models resolved from validated request data (e.g. a target record chosen from a select
input).

## Flash messages via the `->flash()` macro

After mutations, redirect with `to_route(...)->flash('Message.')` (optional `type` and
details args) — always to a named route; a path-based `redirect('/')` is reserved for
logout and dev middleware. The `flash` macro on `RedirectResponse` (defined in
`AppServiceProvider`) feeds the shared `flash` prop that the frontend toast system
consumes. Never use `->with(['message' => ...])`. Scaffolded `Settings/` controllers
still using `->with()` are drift — migrate when touched.

## Thin controllers delegate to services
Controller actions do authorize → DTO from validated input → service call → to_route()->flash(). No Eloquent writes or multi-line queries in controllers; HTTP guards (abort_if/abort_unless) and redirect branchouts are fine.

## $this->authorize() only for actions without a Form Request
Actions that have no Form Request (render pages, archive/restore toggles, index/show) are authorized in the controller with $this->authorize(...) against a Policy. Actions validated by a Form Request rely on its authorize() — do not double-authorize in the controller. Never can: middleware or #[Authorize].

## Inertia render + redirect only — no API Resources
This is an Inertia Svelte app: controllers return Inertia::render with props and to_route() redirects — no API Resources or model returns. response()->json is reserved for exception renderers and the dev-only DataTable endpoint.

## Current user via #[CurrentUser] constructor injection
Inject the user as #[CurrentUser] private readonly ?User $user in constructor promotion; do not call Auth::user()/auth()->user() in action bodies.

# Backend — HTTP Layer

Rules for `app/Http/Controllers/**` and `routes/**`.

## Controllers: singular, multi-method, explicit verb routes

Glob: `app/Http/Controllers/**`, `routes/**`

Default to singular-named, multi-method resource-style controllers (`AccountController`,
not `AccountsController`) with explicit verb routes registered per action — this app does
not use `Route::resource` or invokable controllers. Custom actions (e.g. `archive`,
`restore`, report endpoints) are extra verb routes on the same controller, named
`resource.action`. This is the house default, not a hard rule — deviate when context
warrants.

## Route handlers and route file organization

Glob: `routes/**`

Use controller-class handlers for any route with logic. Closures are acceptable only for
pure redirects, static `Inertia::render()` one-liners, or dev-sandbox pages. Split route
files by concern (`auth.php`, `settings.php`, `dev.php`) and `require` them from
`routes/web.php`.

## Middleware on routes, never on controllers

Glob: `routes/**`

Assign middleware via route groups or per-route `->middleware()`. Never use controller
`HasMiddleware` implementations or `#[Middleware]` attributes. Global adjustments live in
`bootstrap/app.php`.

## Implicit model binding only

Glob: `app/Http/Controllers/**`

Type-hint models in controller action signatures for route params; never use `Route::bind`
or manual `findOrFail` for the primary route model. `findOrFail` is only for secondary
models resolved from validated request data (e.g. the destination account of a transfer).

## Flash messages via the `->flash()` macro

Glob: `app/Http/Controllers/**`

After mutations, redirect with `to_route(...)->flash('Message.')` (optional `type` and
details args). The `flash` macro on `RedirectResponse` (defined in `AppServiceProvider`)
feeds the shared `flash` prop that the frontend toast system consumes. Never use
`->with(['message' => ...])`. Scaffolded `Settings/` controllers still using `->with()`
are drift — migrate when touched.

## Pagination contract

Glob: `app/Http/Controllers/**`, `app/Services/**`

Always standard `->paginate()` — never `simplePaginate()` or `cursorPaginate()`. Pass the
raw `LengthAwarePaginator` as the Inertia prop (no `->toArray()`, no paginator DTO); type
the shape with an inline interface on the Svelte side.

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
details args). The `flash` macro on `RedirectResponse` (defined in `AppServiceProvider`)
feeds the shared `flash` prop that the frontend toast system consumes. Never use
`->with(['message' => ...])`. Scaffolded `Settings/` controllers still using `->with()`
are drift — migrate when touched.

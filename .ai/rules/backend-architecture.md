# Backend — Services & Architecture

Rules for `app/Services/**`, `app/Listeners/**`, `app/Helpers/**`, and cross-cutting
`app/**` conventions.

## Services: static reads, instance writes

Glob: `app/Services/**`

Service read/query methods are declared `static` (`getAccountsByUser()`,
`getCategories()`, `summarize()`) and may be called as `Service::method()` without
injection — including from Inertia shared props. Write methods (`create`, `update`,
`archive`, `restore`, `softDelete` — never `destroy`) are instance methods,
constructor-injected into controllers. Generate new services with
`php artisan make:service` (not `make:repository` — the repository layer is unused in
this app).

## Explicit listener registration

Glob: `app/Listeners/**`

Register listeners explicitly via `Event::listen()` in `AppServiceProvider::boot()` — do
not rely on auto-discovery. One listener may union-type multiple related events in a
single `handle(TransactionSaved | TransactionDeleted $event)` method instead of
duplicating listener classes.

## Helpers are static classes

Glob: `app/Helpers/**`

Helpers are PSR-4 classes with static methods (e.g. `DataTableHelper::parse()`); there
are no global function helper files in composer autoload.

## Explicit query scoping

Glob: `app/Services/**`

Scope reads explicitly at the query site — `where('owner_id', ...)`,
`whereIn('account_id', ...)` — combined with `#[Scope]` named scopes (`notArchived()`,
`shareable()`). Do not add global user-scopes or tenancy scoping to models; per-row
access belongs in Policies.

## Rationale-first PHPDoc

Glob: `app/**`

Non-obvious classes and methods get multi-paragraph block PHPDoc explaining *why* —
constraints, design tradeoffs, alternatives rejected — not just a summary of *what*.
Inline comments are short narrative markers inside docblocked methods. Use
`array{...}` shapes only for positional or complex returns.

## Date idiom

Glob: `app/**`

Get the current time with `now()` for simple values or the `Date::` facade for chained
date-range math (`Date::parse()` for input parsing); never `Carbon::now()`. Import
`Carbon\Carbon` only as a type hint. Date casts are mutable `'datetime'`.

## Money handling

Glob: `app/Services/**`

Keep money as `decimal` casts at the model boundary — `decimal:2` for balances,
`decimal:0` for whole-rupiah IDR transaction amounts. Compute on SQL-aggregated values
with `(float)` casts; `round($x, 2)` for percentages. No `NumberFormatter` in the PHP
layer; formatting belongs to the frontend.

## Iteration idiom

Glob: `app/Services/**`

Map Eloquent/DB row collections with `->map()`; map plain arrays or DTO lists with
`array_map()`; never wrap an array in `collect()` just to chain. Use `foreach` for
mutating or multi-statement loops.

## Report cache TTL strategy

Glob: `app/Services/**`

Report aggregates are cached by recency: past-month data uses `rememberForever()`;
current-month variants use a short TTL (`now()->addMinutes(5)`) since they can still
change. Keys follow `reports:{accountId}:{report}:{year}:{month}`.

## Cross-cutting: explicit per-query eager loading

Glob: `app/**`

Eager loading is explicit at the query site — `->with([...])` / `->load([...])`.
Model-level `$with` defaults are not the house approach; introduce one only with a
deliberate reason, not as a convenience.

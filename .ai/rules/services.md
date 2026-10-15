---
paths:
  - 'app/Services/**'
---

# Services

## Services: static reads, instance writes

Data-access services expose read/query methods as `static` (`getAccountsByUser()`,
`summarize()`) callable without injection — including from Inertia shared props — and
write methods (`create`, `update`, `archive`, `softDelete` — never `destroy`) as
instance methods, constructor-injected into controllers. Transactional or
computation-only services (`TransferService`, `SpendingService`, `BalanceService`) may
be all-instance; pick one posture per service and don't mix static and instance reads
within it. Generate new services with `php artisan make:service` (not
`make:repository` — the repository layer is unused in this app).

## Explicit query scoping

Scope reads explicitly at the query site — `where('owner_id', ...)`,
`whereIn('user_id', ...)` — combined with `#[Scope]` named scopes (`notArchived()`,
`visibleTo()`). Do not add global user-scopes or tenancy scoping to models; per-row
access belongs in Policies.

## List queries return full collections

List flows return Collections via `->get()`; filtering, search, and pagination chunking
happen client-side in Svelte. Do not introduce `->paginate()` on production endpoints —
it exists only in `DataTableHelper` for the dev-only server-mode table.

## Money handling

Keep money as `decimal` casts at the model boundary — choose the scale per column
(`decimal:2` for fractional amounts, `decimal:0` for integer-valued units). Compute on
SQL-aggregated values with `(float)` casts; `round($x, 2)` for percentages. No
`NumberFormatter` in the PHP layer; formatting belongs to the frontend.

## Iteration idiom

Map Eloquent/DB row collections with `->map()`; map plain arrays or DTO lists with
`array_map()`; never wrap an array in `collect()` just to chain. Use `foreach` for
mutating or multi-statement loops.

## Events are dispatched as extension points, without listeners

Services dispatch domain events (`TransactionSaved::dispatch(...)`) purely as extension
points for future reactions — the app deliberately ships **no listeners**; don't
auto-generate listener classes or registration. Model-lifecycle side effects belong in
Observers (see `models.md`), not in listeners.

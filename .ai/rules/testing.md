# Testing

Rules for `tests/**`. Note: Rector does not cover tests — these conventions are
hand-enforced.

## Pest closure tests

Glob: `tests/**`

Write tests as Pest `it('...', function (): void {})` closures — never class-based
PHPUnit tests. Group related cases with `describe()` blocks; use `->with([...])`
datasets for enumerations of inputs.

## Global RefreshDatabase on sqlite memory

Glob: `tests/**`

Rely on the global `RefreshDatabase` binding in `tests/Pest.php` (applied to `Feature`
and `Browser`); don't add per-file `uses()` or manual DB cleanup. Tests run against
sqlite `:memory:` per `phpunit.xml` — do not assume MySQL-specific behavior. `tests/Unit`
stays DB-free (pure logic).

## Factories with named states

Glob: `tests/**`

Build all test data via model factories with named states (`creditCard()`, `income()`,
`transferOut($id)`, `unverified()`) instead of repeating attribute arrays. Never invoke
seeders or insert rows directly in tests.

## Real-integration with selective fakes

Glob: `tests/**`

Hit the real DB, real services, real events and observers. Fake only external delivery
when the test must assert it was sent (`Notification::fake()`). Do not introduce Mockery
mocks or spies.

## Inertia-fluent assertions

Glob: `tests/Feature/**`

Assert Inertia pages fluently: `assertInertia(fn ($page) => $page->component(...)->has(...)->where(...))`.
Assert redirects/forbiddens/validation via chained response methods (`assertRedirect`,
`assertForbidden`, `assertSessionHasErrors` — preferred over `assertInvalid` for Inertia
form posts). Assert persisted state with `expect()` plus an Eloquent re-query, not
`assertDatabaseHas`. Never `assertJson`/`AssertableJson` — there is no JSON API surface.

## Test auth and setup helpers

Glob: `tests/**`

Authenticate inline on the request line: `$this->actingAs(User::factory()->create())`.
Use file-local plain functions for per-file setup (`setupBalanceAccount()`,
`createAccountForUser()`); shared scenarios go in `tests/Pest.php` as global helpers.
Reference all URLs via named `route(...)` calls.

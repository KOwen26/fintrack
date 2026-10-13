# Testing

Rules for `tests/**`. Note: Rector does not cover tests — these conventions are
hand-enforced.

## Pest closure tests

Glob: `tests/**`

Write tests as Pest closures — never class-based PHPUnit tests. `tests/Feature`
uses `test('...', function (): void {})` declarations; `tests/Unit` and
`tests/Integration` use `it('...', function (): void {})`. Keep one style per
file. Group related cases with `describe()` blocks; use `->with([...])`
datasets for enumerations of inputs.

## Global LazilyRefreshDatabase on sqlite memory

Glob: `tests/**`

Rely on the global binding in `tests/Pest.php` — `Tests\TestCase` plus
`LazilyRefreshDatabase`, applied to `Feature`, `Integration`, and `Browser`; don't add
per-file `uses()` or manual DB cleanup there. Tests run against sqlite `:memory:` per
`phpunit.xml` — do not assume MySQL-specific behavior.

## Test taxonomy: Unit / Feature / Integration

Glob: `tests/**`

Three buckets, named by how the test enters the system (not by an isolation gradient —
a Feature test also boots the app; the directories name the entry point):

- `tests/Unit` — pure logic: enums, value objects, support traits. No booted app, no DB.
  Opt in per-file with `uses(TestCase::class)` only when a framework helper is
  unavoidable (e.g. `travelTo()` in `Unit/Objects/DatePeriodTest.php`, the facades in
  `Unit/Support/SushiJsonObserverTest.php`); `Unit/Support/` groups infrastructural
  tests that don't mirror a single `app/` class.
- `tests/Feature` — user-visible behavior through HTTP: `actingAs` requests with
  response, redirect, session, and Inertia assertions. `Feature/Auth` owns the
  authentication surface and the guest/login wiring check (exactly one test — guests
  are never re-tested per route); `Feature/App` holds the authenticated surface, where
  every test acts as a logged-in user.
- `tests/Integration` — booted app with DB, entered by direct class invocation;
  subdirectories mirror `app/` (`Services/`, `Observers/`, `Models/`).

## Factories with named states

Glob: `tests/**`

Build all test data via model factories with named states (`admin()`, `suspended()`,
`unverified()`) instead of repeating attribute arrays. Never invoke
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
Use file-local plain functions for per-file setup (`setupUserTeam()`,
`createTeamForUser()`); shared scenarios go in `tests/Pest.php` as global helpers.
Reference all URLs via named `route(...)` calls.

---
paths:
  - 'tests/**'
---

# Testing

Rector does not cover tests — these conventions are hand-enforced, and the architecture
suite enforces the structural ones (`tests/Architecture/`).

## Suites and entry points

Five suites, named by how the test enters the system (not by an isolation gradient —
the directories name the entry point):

- `tests/Unit` — pure logic (enums, value objects). No booted app, no DB.
- `tests/Feature` — user-visible behavior through HTTP: `actingAs` requests with
  response, redirect, session, and Inertia assertions.
- `tests/Integration` — booted app with DB, entered by direct class invocation;
  subdirectories mirror `app/` (`Services/`, `Observers/`, `Models/`).
- `tests/Architecture` — arch rules over the codebase (presets plus split rules).
- `tests/Browser` — Playwright-driven; only for JavaScript-visible behavior.

`tests/Pest.php` binds `Tests\TestCase` plus `LazilyRefreshDatabase` to `Feature`,
`Integration`, and `Browser` only. Unit tests run on the plain PHPUnit binding; opt in
per file with `pest()->extend(TestCase::class);` only when a framework helper is
unavoidable (`travelTo()`, facades). Every suite directory carries a Pest group;
`Feature/Auth` and `Feature/App` add the `feature:auth` / `feature:app` groups.

## Declarations and grouping

`tests/Feature` uses `test('...', function (): void {})` declarations; `tests/Unit` and
`tests/Integration` use `it('...', function (): void {})`. One declaration style per
file. Group related cases with `describe()` blocks in alphabetical sections; use
`->with([...])` datasets for input enumerations, with named cases.

## Feature layout: Auth vs App

`Feature/Auth` owns the authentication surface and exactly one guest/login wiring
check — guests are never re-tested per route. `Feature/App` holds the authenticated
surface: every test there acts as a logged-in user.

## Module groups

`tests/Feature/App` is organized one directory per module (account, transaction,
transfer, tag, template, dashboard, statistics, settings), and `tests/Pest.php`
maps each directory — plus per-file entries for `tests/Integration` — to a
`module:{name}` group. A new module needs its directory mapped, or
`tests/Architecture/ModuleGroupMapTest.php` fails. Run a feature's full relevance
across suites with `vendor/bin/pest --group=module:{name}`.

## Test data

Build all test data via model factories with named states; never invoke seeders or
insert rows directly. Use file-local plain functions for per-file setup and payload
builders, with globally unique names; shared helpers (`userWithAccount()`,
`pageIds()`) live in `tests/Support/helpers.php` — required once from `Pest.php`,
never re-declared per test file. Any read through a period window uses
`Transaction::factory()->recent()` / `->outsideWindow()` rather than an explicit
date — the factory default is random within the last year and silently falls
outside list windows. Transfer units come from
`Transfer::factory()->withMembers($source, $destination, fee:, sourceTags:)`,
never hand-composed member rows. Freeze the clock with `$this->travelTo()` when
behavior depends on time.

## Shape vs behavior

Scenario factories pin fixture shape. When the scenario itself is the behavior
under test (booking, editing, or deleting a transfer unit), go through the real
service — `resolve(TransferService::class)` — never a factory composition.

## HTTP assertions

Reference all URLs via named `route(...)` calls; authenticate inline on the request
line. Use the named assertions: `assertRedirectToRoute()`, `assertUnprocessable()` for
abort-based 422 guards, `assertSessionHasErrors()` for validation. Assert persisted
state with `expect()` plus an Eloquent re-query, never `assertDatabaseHas`. Write
operations assert response, persisted state, and side effects; refusal paths assert the
state did not change.

## Inertia props

Every render test pins the component name plus prop presence; assert a count only when
the test arranged that many records; use scoped closures with `->etc()` for hand-built
props only. Never re-enumerate DTO-backed prop shapes — the generated types own them.

## Isolation

Hit the real DB, real services, real events and observers. Fake only external delivery
when the test must assert it was sent, and create each fake inside the test that
asserts it. No Mockery mocks or spies. Suite-wide guardrails (stray HTTP, sleep,
exception reporting) live in `tests/TestCase.php`.

## Browser tests

Chain `assertNoJavaScriptErrors()` on every `visit()`. They need Playwright and run
separately from the fast suites; cover only what a Feature test cannot reach.

## Architecture suite

The `php` and `security` presets run as shipped with documented in-file exceptions. The
`laravel` preset is split into one `arch()` block per rule so every violation reports
in a single run, grouped into alphabetical `describe()` sections. App classes are final
by default; the exemption list (`App\Data`, `App\Exceptions`, `App\Helpers`,
`App\Providers`, the abstract base Controller) is deliberate. New exceptions are added
at the rule site with a comment saying why — never as silent blanket ignores.

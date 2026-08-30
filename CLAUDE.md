# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Tech Stack

- **Backend**: PHP 8.4, Laravel 13, Pest 4 (testing)
- **Frontend**: Svelte 5, TypeScript, Inertia.js v2, Tailwind CSS v4, Vite
- **Database**: MySQL (production), SQLite in-memory (tests)
- **Key packages**: Spatie Laravel Data (DTOs), Laravel Wayfinder (typed routes), Ziggy, DaisyUI, bits-ui

## Commands

### Development

```bash
composer dev          # Starts telescope prune + npm dev + pail concurrently
pnpm run dev           # Vite dev server only
```

### Build

```bash
pnpm run build         # Production build
pnpm run build:ssr     # SSR build
```

### Testing

```bash
composer pest         # Run all Pest tests
php artisan test --filter=TestName   # Run a specific test
```

### Linting & Formatting

```bash
composer lint         # Duster (PHPStan) + npm lint:all
composer format       # Duster fix (PHP formatting)
pnpm run lint:all      # Prettier check + ESLint check
pnpm run format:all    # Prettier write + ESLint --fix
pnpm run sv:check      # Svelte type checking
```

### Code Generation

```bash
composer generate:ts  # Transform Laravel Data DTOs → TypeScript types (outputs to resources/js/types/generated.d.ts)
```

## Architecture

### Request Flow

1. Browser → Laravel router (`routes/web.php`, `routes/auth.php`, `routes/settings.php`)
2. Controller calls `Inertia::render('PageName', $data)` passing typed Data objects as props
3. Inertia delivers the Svelte page component with props hydrated on the client

### Backend Structure

- `app/Data/` — Spatie Laravel Data DTOs; run `composer generate:ts` after changes to sync TypeScript types
- `app/Services/` — Business logic (keep controllers thin)
- `app/Repositories/` — Data access layer
- `app/Http/Requests/` — Form validation (use these, not inline `validate()`)
- `app/Enums/` — PHP 8.1+ backed enums; TitleCase keys
- `bootstrap/app.php` — Middleware and exception handling (no `Kernel.php` in Laravel 13)
- Routes are split: `web.php` → requires `auth.php` and `settings.php`; `dev.php` is development-only

### Frontend Structure

- `resources/js/pages/` — Inertia page components (one-to-one with controller renders)
- `resources/js/components/` — Reusable components organized by category: `ui/`, `layouts/`, `data/`, `forms/`, `navigation/`
- `resources/js/svelte/states/` — Global Svelte 5 rune-based state
- `resources/js/svelte/actions/` — Custom Svelte actions
- `resources/js/utilities/` — Helper functions; files ending in `.svelte.ts` use Svelte runes and must be used inside Svelte context
- `resources/js/types/generated.d.ts` — Auto-generated from Laravel Data DTOs (do not edit manually)
- `resources/js/schema/` — Data schemas for validation

### Import Aliases

Configured in `vite.config.js` and `tsconfig.json`:

| Alias         | Path                              |
| ------------- | --------------------------------- |
| `@`           | `resources/js`                    |
| `@components` | `resources/js/components`         |
| `@layouts`    | `resources/js/components/layouts` |
| `@hooks`      | `resources/js/hooks`              |
| `@states`     | `resources/js/svelte/states`      |
| `@utilities`  | `resources/js/utilities`          |
| `@data`       | `resources/js/data`               |
| `@type`       | `resources/js/types`              |
| `@schema`     | `resources/js/schema`             |
| `@wayfinder`  | `resources/js/wayfinder`          |

## Coding Conventions

### PHP

- PHP 8.4 features are available (constructor property promotion, readonly properties, etc.)
- Controllers return `Inertia::render()` — pass Spatie Data objects, not raw arrays
- Use Form Requests for all validation
- Enums use TitleCase keys
- PHPDoc blocks follow Pint spacing rules (configured in `pint.json`)
- Static analysis via Duster/PHPStan — run `composer lint` before committing

### Architecture Patterns

- **DTOs only for complex/combined data** — Spatie Data DTOs are only created when the response shape combines multiple models, carries computed fields, or differs significantly from a single model (e.g. `HouseholdData` joins household + members + user names). Simple model data is passed directly to `Inertia::render()` as an Eloquent model or collection; Wayfinder's generated `App.Models.*` types cover the TypeScript side. Do not create a DTO just to wrap a single model.
- **Service pattern** — all business logic lives in `app/Services/`; controllers are thin dispatchers that call a service and return an Inertia response
- **Event-listener pattern** — use Laravel Events and Listeners for side-effects (e.g. sending a notification after a budget threshold is crossed, updating cache after a transaction is saved); do not trigger side-effects inline inside a service method
- Services own the primary action; listeners own the reactions
- **Aggregates via SQL, never PHP** — balance, budget spend, report totals, and any sum/count over transaction rows must be computed using database aggregate queries (`SUM`, `COUNT`, `selectRaw`); never fetch a collection and reduce in PHP
- **No DB enums** — never use `$table->enum()` in migrations; use `$table->string()` instead and enforce values via PHP-backed enums with Eloquent `$casts`
- **No magic strings in migrations** — when setting a default value for an enum-backed column, use the PHP enum's `.value` property (e.g. `->default(ProviderStatus::Active->value)`, not `->default('active')`). Import the enum at the top of the migration class.
- **Migration column order** — sort columns in this sequence:
    1. `$table->id()`
    2. Relation keys (`foreignId`) — unless the FK is tightly bound to adjacent data columns (e.g. a morph pair `morphable_type` / `morphable_id`), in which case move it next to those columns
    3. Core / grouped data columns (name, amount, type, etc.) — keep related fields together
    4. Status, notes, long-text, and JSON columns
    5. `archived_at`, `deleted_at` (soft delete), then `timestamps()`

### Wayfinder

Wayfinder (`next` branch) auto-generates TypeScript from Laravel controllers, enums, models, and form requests. Run `php artisan wayfinder:generate` after any backend change that affects routes, enums, or models. All output lives under `resources/js/wayfinder/` (alias `@wayfinder`).

**Never hardcode URLs.** Every route call must go through a Wayfinder function.

#### Imports

```typescript
// Controller actions (follows PHP namespace)

// All types (models, enums, shared data, page props)
import type { App } from '@wayfinder/types';

// Enum constants (for runtime comparisons and badge config maps)
import AccountType from '@wayfinder/App/Enums/AccountType';
import { AccountsController } from '@wayfinder/App/Http/Controllers/AccountsController';
// Named routes
import accounts from '@wayfinder/routes/accounts';
```

#### URL generation

```typescript
// URL string — use with Inertia's useForm / router
AccountsController.index.url(); // '/accounts'
AccountsController.show.url({ account: 1 }); // '/accounts/1'

// With query params
AccountsController.index.url({ query: { page: 2 } });
```

#### HTTP method variants — use with Inertia router directly

```typescript
// Inertia useForm — pass .url() to form methods
form.get(AccountsController.index.url());
form.post(AccountsController.store.url());
form.put(AccountsController.update.url({ account: id }));
form.delete(AccountsController.destroy.url({ account: id }));

// Inertia router
router.post(AccountsController.archive.url({ account: id }));
```

#### Form variant — native HTML forms only (not Inertia useForm)

```typescript
// Produces { action: '/accounts/1?_method=PUT', method: 'post' }
// Spread onto a <form> element when NOT using Inertia's useForm
AccountsController.update.form({ account: 1 });
```

#### Typed form requests

```typescript
// Form Request types are generated under the controller namespace
const form = useForm<App.Http.Controllers.AccountsController.Store.Request>({ ... });
```

#### Enum constants

```typescript
// Use Wayfinder-generated constants instead of magic strings
import AccountAccessType from '@wayfinder/App/Enums/AccountAccessType';

// ✅ correct
if (account.access_type === AccountAccessType.Joint) { ... }

// ❌ wrong — magic string
if (account.access_type === 'joint') { ... }
```

#### Enum badge components

Every PHP-backed enum must have a badge component in `resources/js/components/ui/badges/`. Badge config maps use Wayfinder constants as keys so any enum value change propagates automatically.

### Svelte / TypeScript

- **Svelte 5 runes** — use `$state`, `$derived`, `$effect`, `$props` syntax throughout; do not use legacy Options API
- Types come from `@wayfinder/types` — do not use `generated.d.ts` (Wayfinder supersedes it)
- Use Inertia's `useForm` for form state and submission; type it with the generated Form Request type
- Use the `inertia` action directive or `<Link>` for client-side navigation
- Tailwind v4 + DaisyUI for styling; use components over raw HTML wherever possible
- Hooks live in `resources/js/hooks/` (alias `@hooks`); files that use Svelte runes must end in `.svelte.ts`
- **Schema files** — all frontend validation schemas live in `resources/js/schema/` (alias `@schema`); one file per model, named after the model in kebab-case: `account.schema.ts`, `category.schema.ts`, `household.schema.ts`
- **Module components** — feature-specific components live in `resources/js/components/module/{module}/` (e.g. `module/account/account-form.svelte`, `module/account/account-type-badge.svelte`). Reusable UI primitives belong in `components/ui/`. A module component groups everything tied to one domain: its badge variants, its form(s), and any other domain-specific UI. Naming: `{module}-{purpose}.svelte` (e.g. `account-form.svelte`, `account-type-badge.svelte`).
- ESLint 9 flat config enforced on commit via Lefthook
- **File & directory naming** — all `.svelte` and `.ts` files and their containing directories must use `kebab-case` (e.g. `account-card.svelte`, `use-transaction-form.ts`, `components/account-list/`); PascalCase is reserved for component names inside files only

### Git Hooks (Lefthook)

Pre-commit runs automatically:

1. ESLint --fix on `.js`, `.ts`, `.svelte` files
2. Prettier formatting
3. `composer format` (Duster PHP formatting)

Do not bypass with `--no-verify`.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v3
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v13
- laravel/pint (PINT) - v1
- laravel/prompts (PROMPTS) - v0
- laravel/wayfinder (WAYFINDER) - v
- larastan/larastan (LARASTAN) - v3
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/sail (SAIL) - v1
- laravel/telescope (TELESCOPE) - v5
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- rector/rector (RECTOR) - v2
- @inertiajs/svelte (INERTIA_SVELTE) - v3
- eslint (ESLINT) - v10
- prettier (PRETTIER) - v3
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `pnpm run build`, `pnpm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
    - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Follow existing application Enum naming conventions.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-svelte-development` when working with Inertia Svelte client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `pnpm run build` or ask the user to run `pnpm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== wayfinder/v rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== inertia-svelte/core rules ===

# Inertia + Svelte

- IMPORTANT: Activate `inertia-svelte-development` when working with Inertia Svelte client-side patterns.

</laravel-boost-guidelines>

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
=== .ai/laravel-inertia-svelte/rules/inertia-svelte-frontend rules ===

# Inertia + Svelte Frontend Rules

> **About the examples:** Code samples use a neutral sample domain (`User`, `Team`, `UserStatus`) purely to illustrate the conventions — these are shapes any Laravel app ships with, not this app's business scope. They are **not** a domain spec: never assume models, columns, or enum values from these docs. Always derive the real domain shape from the actual code and the Wayfinder-generated types (`@wayfinder/*`).

## Svelte 5 Runes

Always use Svelte 5 rune syntax. Never use the legacy Options API (`export let`, `$:`, reactive statements).

```svelte
<script lang="ts">
    // ✅ runes
    let { user, teams } = $props();
    let showConfirm = $state(false);
    const isEdit = $derived(!!user);
    $effect(() => {
        document.title = user.name;
    });

    // ❌ legacy Options API
    export let user;
    $: isEdit = !!user;
</script>
```

## File & Directory Naming

All `.svelte` and `.ts` files and their containing directories must use `kebab-case`.

- `user-form.svelte` ✅ — `UserForm.svelte` ❌
- `use-user-avatar.svelte.ts` ✅ — `useUserAvatar.ts` ❌
- `components/user-list/` ✅ — `components/UserList/` ❌

PascalCase is reserved for component names _inside_ files only (`<UserForm />`).

Files that use Svelte runes outside a `.svelte` file must end in `.svelte.ts`.

## Directory Conventions

| Purpose                   | Location                                   |
| ------------------------- | ------------------------------------------ |
| Inertia page components   | `resources/js/pages/`                      |
| Reusable UI primitives    | `resources/js/components/ui/`              |
| Atomic UI sub-components  | `resources/js/components/ui/atoms/`        |
| Feature module components | `resources/js/components/module/{module}/` |
| Layouts                   | `resources/js/components/layouts/`         |
| Navigation                | `resources/js/components/navigation/`      |
| Data display              | `resources/js/components/data/`            |
| Svelte hooks              | `resources/js/hooks/`                      |
| DataComposer schemas      | `resources/js/schema/`                     |
| Wayfinder generated files | `resources/js/wayfinder/`                  |
| Global rune-based state   | `resources/js/svelte/states/`              |
| Svelte actions            | `resources/js/svelte/actions/`             |
| Utility helpers           | `resources/js/utilities/`                  |
| Theme / color data        | `resources/js/data/`                       |

## Layout Assignment

Layouts are assigned globally in `resources/js/app.ts` via a `layout()` switch on the page name prefix. Never declare `layout` inside a page component — use the central switch.

```typescript
// resources/js/app.ts
layout: (name) => {
    switch (true) {
        case name.startsWith('users'):
        case name.startsWith('teams'):
        case name.startsWith('settings'):
            return DashboardLayout;

        default:
            return null;
    }
},
```

The switch must enumerate **every** app-page prefix the app uses and map it to `DashboardLayout` — a sidebar + header layout that calls `useFlashToast()` and reads `page.props.meta.current_route_name` for breadcrumbs — returning `null` for everything else. When you add a new page module, add its prefix to this switch. Auth pages get `null` and self-wrap their content in `AuthLayout`; the landing page self-wraps in `BaseLayout`. Never declare a layout inside a page component. `AppLayout` is unused — do not reference it.

## Page Props

Use typed inline destructuring for `$props()`. Always import `App` from `@wayfinder/types`. Never use `generated.d.ts`.

```svelte
<!-- resources/js/pages/users/index.svelte -->
<script lang="ts">
    import type { App } from '@wayfinder/types';

    let { users }: { users: App.Models.User[] } = $props();
</script>
```

For non-model prop shapes (e.g. paginator wrappers), define an inline interface:

```svelte
<!-- resources/js/pages/users/show.svelte -->
<script lang="ts">
    import type { App } from '@wayfinder/types';

    interface PaginatedLogins {
        data: App.Models.Login[];
        links: { url: string | null; label: string; active: boolean }[];
        current_page: number;
        last_page: number;
    }

    let {
        user,
        logins,
        storage_used,
    }: {
        user: App.Models.User;
        logins: PaginatedLogins;
        storage_used: string;
    } = $props();
</script>
```

DTOs from Spatie Laravel Data appear as `App.Data.*` types in page props.

## Shared Page Props

Wayfinder generates `inertia-config.d.ts` which augments `@inertiajs/core`. These shared props are always on `page.props`:

```typescript
{
    csrf_token: string | null;
    auth: {
        user: unknown;
        permissions: [] | null;
    }
    flash: {
        type: unknown;
        message: unknown;
        details: unknown;
    }
    meta: {
        app_name: unknown;
        current_route_name: string | null;
        previous_route_name: string | null;
    }
}
```

Cast `auth.user` to `App.Models.User | null` when accessing user fields:

```typescript
(page.props.auth?.user as App.Models.User | null)?.theme_preference;
```

## Module Components

Feature-specific components live in `resources/js/components/module/{module}/`. Each module directory groups all domain-specific UI: badge variants, forms, and any other domain UI. When you add a new module, create its directory here.

Naming: `{module}-{purpose}.svelte`

```
components/module/
  user/
    user-form.svelte
    user-status-badge.svelte
  team/
    team-form.svelte
    team-invite-form.svelte
    team-member-role-badge.svelte
```

Pages import module components instead of inlining logic:

```svelte
<!-- resources/js/pages/users/create.svelte -->
<UserForm {team_id} />

<!-- resources/js/pages/users/edit.svelte -->
<UserForm
    {user}
    onCancel={() => router.visit(UserController.show.url({ user: user.id }))} />
```

## Enum Badge Components

Every PHP-backed enum must have a badge component in `components/module/{module}/`. Badges wrap `Badge` (`@components/ui/badge.svelte`) with a config map keyed by Wayfinder enum constants.

```svelte
<!-- resources/js/components/module/user/user-status-badge.svelte -->
<script lang="ts">
    import type { ColorVariant } from '@/data/theme';
    import type { App } from '@wayfinder/types';

    import UserStatus from '@wayfinder/App/Enums/UserStatus';

    import Badge from '@components/ui/badge.svelte';

    let { status }: { status: App.Enums.UserStatus } = $props();

    const config: Record<App.Enums.UserStatus, { label: string; color: ColorVariant }> = {
        [UserStatus.Active]: { label: 'Active', color: 'success' },
        [UserStatus.Trial]: { label: 'Trial', color: 'info' },
        [UserStatus.Suspended]: { label: 'Suspended', color: 'warning' },
        [UserStatus.Banned]: { label: 'Banned', color: 'error' },
    };

    const badge = $derived(config[status]);
</script>

<Badge color={badge.color} variant="soft">{badge.label}</Badge>
```

Computed status badges not backed by a Wayfinder enum use a local string union type instead:

```svelte
<!-- resources/js/components/module/team/invite-state-badge.svelte -->
type InviteState = 'pending' | 'accepted' | 'expired';
let { state }: { state: InviteState } = $props();
```

The config map uses Wayfinder constants as keys — TypeScript will error if an enum value changes without updating the map.

## Data Schemas (DataComposer)

One schema file per model in `resources/js/schema/`, named `{model}.schema.ts`. Schemas use the in-house `DataComposer` system (`@utilities/data-composer`). A single `DataSchema` drives form fields, display values, and table columns from one definition.

A schema file may export multiple schemas when a module has multiple distinct forms with different shapes (e.g. `team.schema.ts` exports both `teamSchema` and `teamInviteSchema`).

```typescript
// resources/js/schema/user.schema.ts
import type { DataSchema } from '@utilities/data-composer';
import type { App } from '@wayfinder/types';

import UserStatus from '@wayfinder/App/Enums/UserStatus';

export const userSchema: DataSchema<App.Models.User> = {
    name: {
        label: 'Name',
        table: true, // include in toDatatableColumn()
        form: () => ({
            type: 'text',
            name: 'name',
            required: true,
            inputProps: { placeholder: 'e.g. Jane Doe', autocorrect: 'off' },
        }),
    },
    email: {
        label: 'Email',
        table: true,
        form: () => ({
            type: 'email',
            name: 'email',
            required: true,
            inputProps: { placeholder: 'user@example.com' },
        }),
    },
    trial_ends_at: {
        label: 'Trial Ends At',
        value: (data) => new Date(data.trial_ends_at).toLocaleDateString(), // display formatter
        show: (data) => data.status === UserStatus.Trial, // conditional display
        form: () => ({
            type: 'date',
            name: 'trial_ends_at',
            show: (form: any) => form.status === UserStatus.Trial, // conditional form field
        }),
    },
};
```

### DataSchemaItem properties

| Property      | Type                                       | Purpose                                            |
| ------------- | ------------------------------------------ | -------------------------------------------------- |
| `label`       | `string`                                   | Human-readable field label                         |
| `value`       | `any \| (data) => any`                     | Display value / formatter                          |
| `form`        | `(data?) => FormGeneratorProps`            | Form field config factory                          |
| `table`       | `boolean \| TableProps`                    | Include in datatable columns                       |
| `tableFilter` | `boolean \| (data?) => FormGeneratorProps` | Filter config; `true` falls back to `form` factory |
| `show`        | `boolean \| (data) => boolean`             | Conditional visibility for display and form fields |
| `class`       | `string`                                   | CSS class applied to the display value             |
| `meta`        | `AnyRecord`                                | Arbitrary extra data attached to the field         |

### DataComposer full API

`DataComposer` is a fluent builder. All filter/mutation methods return `this` for chaining.

**Static factory methods**

| Method                                    | Description                                                       |
| ----------------------------------------- | ----------------------------------------------------------------- |
| `DataComposer.from(schema, data?)`        | Create instance from explicit schema                              |
| `DataComposer.fromData(data)`             | Auto-generate schema from object keys (snake_case → human labels) |
| `DataComposer.toSchema(schema, options?)` | Filter/order a schema without an instance                         |
| `DataComposer.mergeSchema(...schemas)`    | Merge multiple schemas; later wins                                |

**Instance filter / order methods**

| Method                  | Description                                            |
| ----------------------- | ------------------------------------------------------ |
| `.only(keys)`           | Whitelist fields (mutually exclusive with `.except()`) |
| `.except(keys)`         | Blacklist fields (mutually exclusive with `.only()`)   |
| `.order(keys)`          | Reorder — specified keys appear first                  |
| `.setData(data)`        | Replace the data context                               |
| `.clone()`              | Deep-copy the instance                                 |
| `.getSchema(override?)` | Return the final filtered schema                       |

**Schema mutation methods**

| Method                     | Description                                                                                                         |
| -------------------------- | ------------------------------------------------------------------------------------------------------------------- |
| `.extendSchema(extension)` | Merge schema items — `form` factories are composed (extension wins on conflicts), other properties shallow-replaced |
| `.overrideSchema(schema)`  | Replace matching schema items wholesale (drops inner props not re-specified)                                        |

**Terminal methods**

| Method                                            | Returns                              | Use for                                   |
| ------------------------------------------------- | ------------------------------------ | ----------------------------------------- |
| `.toDataDisplay(data?, override?)`                | `DataDisplay[]`                      | Feed `<DataList>` or `<DataGrid>`         |
| `.toFormGenerator(defaults?, options?)`           | `{ fields, data }`                   | Pass as `formSchema` to `<FormGenerator>` |
| `.toFormFields(data?, options?)`                  | `Record<string, FormGeneratorProps>` | Fields without data                       |
| `.toFormData(defaults?)`                          | `Record<string, any>`                | Seeded form data                          |
| `.toDatatableColumn(options?, override?)`         | `ColumnDef[]`                        | TanStack Table column definitions         |
| `.toDatatable(data?, colOpts?, opts?, override?)` | `DataTable`                          | Full TanStack Table instance              |
| `.toDatatableFilters(override?)`                  | `{ name, label, form }[]`            | Filter form configs                       |
| `.get(key, data?)`                                | Single field object                  | One field's display + form data           |
| `.getAll(data?, override?)`                       | Record of field objects              | All fields as display + form objects      |

### DataComposer usage in components

```svelte
<!-- resources/js/components/module/user/user-form.svelte -->
<script lang="ts">
    import { userSchema } from '@schema/user.schema';

    import { DataComposer } from '@utilities/data-composer';

    // formSchema is a $derived wrapping a function — invoke in the template: formSchema()
    const formSchema = $derived(() => {
        const composer = DataComposer.from(userSchema).extendSchema({
            team_id: {
                label: 'Team (optional)',
                form: () => ({ type: 'select', name: 'team_id', options: teamOptions }),
            },
        });

        if (isEdit && user) {
            return composer.except(['status', 'trial_ends_at']).toFormGenerator({
                name: user.name,
                email: user.email,
                team_id: user.team_id ?? '',
            });
        }

        const { fields, data } = composer.toFormGenerator({
            status: UserStatus.Active,
            trial_ends_at: null,
        });

        // Hidden values not in fields are still submitted
        return { fields, data: { ...data, team_id: team_id ?? '' } };
    });
</script>

<FormGenerator formSchema={formSchema()} ... />
```

`$derived(() => { ... })` (a derived wrapping a function factory) is used instead of plain `$derived(...)` when the expression calls `DataComposer.from()` and may need to re-evaluate as reactive dependencies change. Always invoke the result when passing to a prop: `formSchema={formSchema()}`.

```svelte
<!-- resources/js/pages/users/show.svelte -->
<script lang="ts">
    // Display: feed DataList
    const details = $derived(
        DataComposer.from(userSchema)
            .except(['status', 'name'])
            .toDataDisplay(user)
    );
</script>

<DataList data={details} />
```

## Forms

### FormGenerator

Use `FormGenerator` (`@components/ui/forms/form-generator.svelte`) for all create/edit forms. It creates a `useForm` instance internally from `formSchema.data`, renders `FieldInput` for each field, and handles conditional visibility and disabled state.

```svelte
<Card>
    <FormGenerator
        id="user-form"
        {action}
        formSchema={formSchema()}
        method="put"
        withoutSubmit
        bind:form />
</Card>

<div class="mt-4">
    <FormAction
        {form}
        formId="user-form"
        labelSubmit="Save Changes"
        labelCancel="Cancel"
        onCancel={onCancel ?? (() => window.history.back())} />
</div>
```

Key patterns:

- `bind:form` — exposes the `useForm` instance to the parent for use in `FormAction`
- `withoutSubmit` + external `FormAction` with `formId` — submit button lives outside the `<form>` tag via the HTML `form` attribute
- `method` prop — pass `"put"` for updates; omit for `"post"` (default)
- `submitOptions` — Inertia submit options (e.g. `{ onSuccess: () => onSuccess?.() }`)
- `data` keys not in `fields` are still submitted (use for hidden values like `team_id`)
- `show: (form) => bool` in a field definition makes the field conditionally visible
- `disabledFn: (form) => bool` in a field definition makes the field conditionally disabled

### FormGenerator grid variants

The `variant` prop controls layout:

| variant   | layout             |
| --------- | ------------------ |
| `default` | single column      |
| `grid-2`  | 2 columns on `md+` |
| `grid-3`  | 3 columns on `md+` |
| `grid-4`  | 4 columns on `md+` |

### Supported field types

`FormGeneratorProps.type` values and what they render:

| `type`                             | Renders                                    |
| ---------------------------------- | ------------------------------------------ |
| `text`, `email`, `number`, `input` | `<Input>`                                  |
| `password-input`                   | `<PasswordInput>`                          |
| `phone-input`                      | `<PhoneInput>`                             |
| `textarea`                         | `<Textarea>`                               |
| `masked-input`                     | `<MaskedInput>`                            |
| `date`                             | `<DateInput>`                              |
| `file`                             | `<FileInput>`                              |
| `select`                           | `<Select items={options}>`                 |
| `checkbox`                         | `<CheckboxGroup>` + `<Checkbox>` items     |
| `radio`                            | `<RadioGroup>` + `<RadioGroupItem>` items  |
| `switch`                           | `<Switch>`                                 |
| `raw`                              | `<FlexRender>` with a component or snippet |

### Form component

Use `Form` (`@components/ui/forms/form.svelte`) for simple single-action forms that don't warrant `FormGenerator`:

```svelte
<Form
    form={acceptForm}
    action={TeamInvitationController.accept.url({ token: invitation.token })}>
    <SubmitButton class="w-full" submitting={acceptForm.processing}>Accept Invitation</SubmitButton>
</Form>
```

`Form` defaults to `method="post"` and `preserveScroll: true`. Pass `method` for other verbs. It injects a hidden `_method` field for method spoofing (PUT/PATCH/DELETE).

### FormAction

`FormAction` (`@components/ui/forms/form-action.svelte`) renders a Cancel + Submit button row:

```svelte
<FormAction
    {form}
    formId="user-form"
    labelSubmit="Create User"
    labelCancel="Cancel"
    onCancel={() => window.history.back()}
    withoutCancel={false} />
```

- Default `labelSubmit` is `'Simpan'`, default `labelCancel` is `'Batal'`
- `withoutCancel={true}` hides the cancel button
- `onCancel` defaults to `window.history.back()`
- `formId` links the submit button to an external `<form>` via the HTML `form` attribute

### Confirmation dialogs

Replace all browser `confirm()` calls with `ConfirmationModal` (`@components/ui/modals/confirmation-modal.svelte`).

`bind:open` accepts a boolean or a nullable ID — it is truthy-evaluated to open the modal:

```svelte
<!-- resources/js/pages/users/edit.svelte -->
<script lang="ts">
    let showDeleteConfirm = $state(false);

    function destroy() {
        router.delete(UserController.destroy.url({ user: user.id }));
    }
</script>

<ConfirmationModal
    title="Delete User"
    confirmText="Delete"
    cancelText="Cancel"
    confirmButtonProps={{ color: 'error' }}
    onConfirm={destroy}
    bind:open={showDeleteConfirm}>
    This will permanently delete the user and cannot be undone.
</ConfirmationModal>

<Button color="error" variant="outline" onclick={() => (showDeleteConfirm = true)}>Delete</Button>
```

Pattern with nullable ID state (for list items):

```svelte
<!-- resources/js/pages/teams/index.svelte -->
let deletingMemberId = $state<number | null>(null);

function destroyMember() {
    if (!deletingMemberId) return;
    router.delete(TeamController.destroy.url({ team: team.id, member: deletingMemberId }), {
        onFinish: () => (deletingMemberId = null),
    });
}

<ConfirmationModal
    onCancel={() => (deletingMemberId = null)}
    onConfirm={destroyMember}
    bind:open={deletingMemberId}>
    ...
</ConfirmationModal>
```

### DetailActionModal

`DetailActionModal` (`@components/ui/modals/detail-action-modal.svelte`) supports a view/edit mode toggle in a single modal. The `children` snippet receives the current `mode`:

```svelte
<DetailActionModal
    bind:open={showModal}
    bind:mode
    title="User"
    {action}
    onSubmit={handleSubmit}>
    {#snippet children(mode)}
        {#if mode === 'view'}
            <DataList data={details} />
        {:else}
            <FormGenerator ... />
        {/if}
    {/snippet}
</DetailActionModal>
```

## UI Components Reference

### Button

```svelte
<!-- Solid (default) -->
<Button color="primary">Save</Button>

<!-- Variants: solid | outline | ghost | soft | link -->
<Button color="error" variant="outline">Delete</Button>

<!-- As Inertia-navigating anchor (default when href provided) -->
<Button href={UserController.create.url()} color="primary" size="sm">
    <i class="iconify size-4 solar--add-bold-duotone"></i> Add
</Button>

<!-- Circle icon button -->
<Button class="btn-circle btn-sm" color="light" variant="ghost">
    <i class="iconify size-5 solar--arrow-left-line-duotone"></i>
</Button>

<!-- Sizes: default | sm | lg | icon -->

<!-- Disable Inertia (plain <a>) -->
<Button href="/external" withoutInertia>External</Button>

<!-- Use router.visit() instead of use:inertia directive -->
<Button href={url} useRouter={{ preserveScroll: true }}>Navigate</Button>
```

`ColorVariant` is exported from `@/data/theme`: `primary`, `secondary`, `accent`, `success`, `info`, `warning`, `error`, `light`, `dark`.

### Badge

```svelte
<!-- Variants: solid | outline | outline-dash | soft -->
<!-- Shapes: square | rounded (default) | pill -->
<Badge color="success" variant="soft">Active</Badge>
<Badge color="warning" variant="outline-dash" shape="pill">At Risk</Badge>
```

Import `ColorVariant` from `@/data/theme` for badge config maps.

### Card

```svelte
<!-- Basic -->
<Card>content</Card>

<!-- With string title -->
<Card title="User Details">content</Card>

<!-- With snippet title -->
<Card>
    {#snippet title()}<span class="text-primary">Custom</span>{/snippet}
    content
</Card>

<!-- With header action slot -->
<Card title="Overview">
    {#snippet headerAction()}
        <Button class="btn-xs" color="light" variant="ghost" href={url}>View all</Button>
    {/snippet}
    content
</Card>

<!-- class targets the outer div -->
<Card class="mb-4 bg-primary text-primary-content">content</Card>
```

### DataList

Accepts `DataDisplay[]` produced by `DataComposer.toDataDisplay()`. Supports `prepend` and `append` snippets for injecting custom rows:

```svelte
<DataList data={details} />

<DataList data={details}>
    {#snippet append()}
        <div class="border-t pt-2">
            <UserStatusBadge status={user.status} />
        </div>
    {/snippet}
</DataList>
```

Rows with `type: 'heading'` render as a section heading rather than a key-value pair.

## Wayfinder

All generated files live under `resources/js/wayfinder/` (alias `@wayfinder`). Run `php artisan wayfinder:generate` after any backend change. **Never hardcode URLs.**

### Imports

```typescript
// Controller — always default import (the named export object)

// All model / enum / DTO types
import type { App } from '@wayfinder/types';

// Enum constants for runtime comparisons and badge config maps
import UserStatus from '@wayfinder/App/Enums/UserStatus';
import UserController from '@wayfinder/App/Http/Controllers/UserController';
// Named routes (rarely needed; prefer controller imports)
import users from '@wayfinder/routes/users';
```

### URL generation

```typescript
UserController.index.url(); // '/users'
UserController.show.url({ user: 1 }); // '/users/1'
UserController.index.url({ query: { page: 2 } }); // '/users?page=2'
```

### With Inertia — always use `.url()`

```typescript
// FormGenerator / Form component action prop
action={UserController.store.url()}
action={UserController.update.url({ user: user.id })}

// router for non-form navigation and actions
router.delete(TeamController.destroy.url({ team: id }));
router.post(UserController.restore.url({ user: id }));
router.visit(
    UserController.index.url({ query: { page: 2 } }),
    { preserveState: false }
);
```

### `.form()` — native HTML forms only

```typescript
// Produces { action: '/users/1?_method=PUT', method: 'post' }
// Only use when spreading onto a native <form> — NOT with Inertia Form/FormGenerator
UserController.update.form({ user: 1 });
```

### Enum constants — no magic strings

```typescript
// ✅ correct
if (user.status === UserStatus.Trial) { ... }
const form = useForm({ status: UserStatus.Active });

// ❌ wrong
if (user.status === 'trial') { ... }
```

## Hooks

Hooks live in `resources/js/hooks/` (alias `@hooks`). Files that use Svelte runes must end in `.svelte.ts`.

Return reactive values using getter syntax so reactivity is preserved across destructuring:

```typescript
// resources/js/hooks/use-theme.svelte.ts
export function useTheme() {
    const current = $derived(
        (page.props.auth?.user as App.Models.User | null)?.theme_preference ?? 'light'
    );

    $effect(() => {
        document.documentElement.dataset.theme = current;
    });

    return {
        get current() {
            return current;
        },
    };
}
```

### Available hooks

| Export                   | File                      | Purpose                                                                                 |
| ------------------------ | ------------------------- | --------------------------------------------------------------------------------------- |
| `useTheme()`             | `use-theme.svelte.ts`     | Reads `auth.user.theme_preference`; applies to `document.documentElement.dataset.theme` |
| `useFlashToast()`        | `flash-handler.svelte.ts` | Watches `page.props.flash` and fires `toast[type](message)` on change                   |
| `initializeFlashToast()` | `flash-handler.svelte.ts` | Wires `router.on('flash', ...)` at app boot                                             |
| `useUrlHandler()`        | `url-handler.svelte.ts`   | Returns `currentUrl`, `isCurrentUrl()`, `isCurrentOrParentUrl()`, `whenCurrentUrl()`    |

`useFlashToast()` is called inside `AuthLayout` and `DashboardLayout` — do not call it again in individual page components.

## Global Reactive State

Module-level `$state` objects in `resources/js/svelte/states/` are shared across components without prop drilling. Always import by name from the state file:

```typescript
// resources/js/svelte/states/reactive.svelte.ts
export const sidebar = $state({
    is_collapsed: localStorage.getItem('sidebar-collapse') === 'true' || false,
    collapse() {
        this.is_collapsed = !this.is_collapsed;
        localStorage.setItem('sidebar-collapse', this.is_collapsed.toString());
    },
});
```

## Components Over Raw HTML

Use existing components instead of raw HTML/CSS wherever possible:

| Instead of                         | Use                                                            |
| ---------------------------------- | -------------------------------------------------------------- |
| `<button class="btn btn-primary">` | `<Button color="primary">`                                     |
| `<a href>` / `<Link>`              | `<Button href="...">` (Inertia nav built in)                   |
| `<div class="card">`               | `<Card title="...">`                                           |
| `<fieldset>` + raw `<input>`       | `<FormGenerator formSchema={...} ...>`                         |
| `<span class="badge">`             | `<Badge color="...">` or module badge component                |
| Manual key-value rows              | `<DataList data={DataComposer.from(schema).toDataDisplay(x)}>` |
| `browser confirm()`                | `<ConfirmationModal bind:open onConfirm>`                      |
| Raw `<form>`                       | `<Form form={...} action={...}>`                               |

`<Link>` from `@inertiajs/svelte` and `use:inertia` on raw `<a>` tags both exist in the codebase (e.g. pagination links). Prefer `<Button href>` for new code; use `use:inertia` only when you need a non-button element.

## Icons

Icons use the `iconify` CSS class with Solar icons (`solar--` prefix, primary) or Tabler icons (`tabler--`, secondary). The default weight suffix is `-bold-duotone`; the outline variant is `-line-duotone`. Only `solar` and `tabler` are enabled in the `@iconify/tailwind4` plugin config in `resources/css/app.css` — no other icon set is installed:

```svelte
<i class="iconify size-5 solar--arrow-left-line-duotone"></i>
<i class="iconify size-4 solar--add-bold-duotone"></i>
<i class="iconify size-12 solar--user-bold-duotone"></i>
```

Use DaisyUI size utilities: `size-4`, `size-5`, `size-6`, `size-10`, `size-12`.

=== .ai/laravel-inertia-svelte/rules/laravel-backend rules ===

# Laravel Backend Rules

> **About the examples:** Code samples use a neutral sample domain (`User`, `Team`, `UserStatus`) purely to illustrate the conventions — these are shapes any Laravel app ships with, not this app's business scope. They are **not** a domain spec: never assume models, columns, or enum values from these docs. Always derive the real domain shape from the actual code and the Wayfinder-generated types (`@wayfinder/*`).

## PHP Conventions

- PHP 8.4 — use constructor property promotion, readonly properties, first-class callables
- Always declare explicit return types and typed parameters: `function create(User $actor, array $data): User`
- Use curly braces on all control structures, even single-line bodies
- Enums: backed string enums, TitleCase case names — `case Active = 'active'`
- PHPDoc blocks for complex return types (e.g. `@return array{pruned: int, failed: int}`); inline comments only for non-obvious invariants

## Architecture Patterns

### Service Pattern

All business logic lives in `app/Services/`. Controllers are thin dispatchers that call one service method and return an Inertia response. Never put queries, calculations, or conditional logic directly in a controller.

```php
// ✅ correct — app/Http/Controllers/UserController.php
class UserController extends Controller
{
    public function __construct(private readonly UserService $userService) {}

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = $this->userService->create($request->user(), $request->validated());
        return to_route('users.show', $user)->flash('User created.');
    }
}

// ❌ wrong — logic in controller
public function store(Request $request): RedirectResponse
{
    $user = User::create([...$request->validated(), 'team_id' => $request->user()->team_id]);
}
```

Services can inject other services when needed:

```php
// app/Services/UserService.php
class UserService
{
    public function __construct(private readonly TeamService $teamService) {}
}
```

### Event-Listener Pattern

Side-effects (cache invalidation, notifications, post-save hooks) live in Listeners attached to Events — never inline inside a service method. Services fire an event after the primary action; listeners react.

```php
// app/Services/UserService.php — service fires event, does NOT touch cache
class UserService
{
    public function update(User $actor, User $user, array $data): User
    {
        $user->update($data);
        UserSaved::dispatch($user);
        return $user;
    }
}

// app/Listeners/InvalidateUserCache.php — listener owns the side-effect
class InvalidateUserCache
{
    // Union type — one handler for two related events
    public function handle(UserSaved | UserDeleted $event): void
    {
        Cache::tags(['user:' . $event->user->id])->flush();
    }

    // Named handler for a third, structurally different event on the same listener
    public function handleTeamMemberAdded(TeamMemberAdded $event): void
    {
        Cache::tags(['team:' . $event->team->id])->flush();
    }
}
```

### DB Transactions for Multi-Step Writes

Wrap operations that must succeed or fail together in `DB::transaction()`:

```php
// app/Services/UserService.php
public function createWithInvite(User $actor, array $data): User
{
    return DB::transaction(function () use ($actor, $data): User {
        $user = $this->create($actor, [...]);

        if ($data['send_invite'] ?? false) {
            $this->teamService->sendInvite($user);
        }

        return $user;
    });
}
```

Each iteration of a loop that can partially fail should wrap its own `DB::transaction` with a try/catch.

### Aggregates via SQL — Never PHP

Report totals, status counts, and any sum/count over rows must be computed using SQL aggregates. Never fetch a collection and reduce it in PHP.

```php
// ✅ correct — count by status in SQL
$activeCount = DB::table('users')
    ->selectRaw(
        'SUM(CASE WHEN users.status IN (?, ?) THEN 1 ELSE 0 END) AS active_count',
        [UserStatus::Active->value, UserStatus::Trial->value]
    )
    ->whereNull('users.deleted_at')
    ->value('active_count');

// ❌ wrong — PHP reduction
$count = 0;
foreach (User::all() as $user) { ... }
```

## Controllers

- Return `Inertia::render('page/name', [...])` passing **raw Eloquent models** — not DTOs (DTOs only for cross-model shapes, see Spatie Data section)
- Use `$this->authorize()` for every write action and `view` checks on resource pages
- Use `abort_unless()` for quick business-rule guards that don't warrant a policy
- Use `to_route()` for redirects after mutations; use `back()` when there's no single canonical destination
- Inject services via constructor property promotion

```php
// to_route() — after create/update/destroy with a known destination
return to_route('users.show', $user)->flash('User created.');

// back() — after actions like invites where destination varies
return back()->flash('Invitation sent.');

// abort_unless() — quick guard before policy check
abort_unless($invite !== null, 404);
$this->authorize('accept', $invite);
```

Authorize with extra model context (second arg to `[ChildModel::class, $context]`):

```php
// app/Http/Controllers/ActivityController.php
$this->authorize('viewAny', [Activity::class, $user]);
$this->authorize('create', [Activity::class, $user]);
```

## Form Requests

All validation lives in `app/Http/Requests/`. Never use inline `$request->validate()`.

```php
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'   => ['required', 'string', 'max:255'],
            'email'  => ['required', 'string', 'email', 'max:255'],
            'status' => ['required', 'string', Rule::enum(UserStatus::class)],
        ];
    }
}
```

## Eloquent Models

- Use `SoftDeletes` on any model that soft-deletes
- Use `protected function casts(): array` (method form, not `$casts` property)
- Cast enum-backed columns to PHP enum classes; never use raw strings in code
- Use `$guarded = []` on all models (mass-assignment open, rely on Form Requests); exception: `User` uses explicit `$fillable`
- Use `#[Scope]` attribute on `protected function` for named scopes — **not** the old `scopeName()` naming convention
- Domain behavior that belongs to the model (e.g. date math, derived state) goes as a public method on the model

```php
// app/Models/User.php
class User extends Authenticatable
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'email', 'password', 'status'];

    protected function casts(): array
    {
        return [
            'status'            => UserStatus::class,
            'email_verified_at' => 'datetime',
            'settings'          => 'array',
        ];
    }

    #[Scope]
    protected function visibleTo(Builder $query, User $actor): Builder
    {
        // ...returns query filtered to users the actor can see
    }
}

// All other models use $guarded = []:
// app/Models/Team.php
class Team extends Model
{
    protected $guarded = [];
    // ...
}

// Domain behavior on the model (date math)
public function gracePeriodEndsOn(Carbon $suspendedAt): Carbon
{
    return match ($this->status) {
        UserStatus::Suspended => $suspendedAt->addDays(30),
        UserStatus::Trial     => $suspendedAt->addDays(7),
        // ...
    };
}

// Disable timestamps when not needed
// app/Models/TeamUser.php
public $timestamps = false;
```

Use `#[UseFactory]` attribute to bind a factory to a model:

```php
// app/Models/User.php
#[UseFactory(UserFactory::class)]
class User extends Authenticatable { ... }
```

## Enums

Backed string enums with TitleCase case names. Add static helper methods to return semantic value subsets used in SQL and validation:

```php
// app/Enums/UserStatus.php
enum UserStatus: string
{
    case Active    = 'active';
    case Trial     = 'trial';
    case Suspended = 'suspended';
    case Banned    = 'banned';

    /** @return array<string> Statuses counted toward the active-user metric */
    public static function activeStates(): array
    {
        return [self::Active->value, self::Trial->value];
    }

    /** @return array<string> Statuses that may still sign in */
    public static function loginableStates(): array
    {
        return [self::Active->value, self::Trial->value, self::Suspended->value];
    }
}
```

## Policies

Every resource that requires authorization has a Policy. Register automatically via model discovery.

- Extract shared access logic into a private `canAccess()` method
- Delegate to a related model's policy via `$actor->can('view', $relatedModel)` rather than re-implementing ownership checks
- Custom methods beyond CRUD are fine: `archive`, `restore`, `invite`, `removeMember`, `toggle`

```php
// app/Policies/UserPolicy.php — custom method + private extractor
public function impersonate(User $actor, User $user): bool
{
    return $actor->is_admin;
}

private function canAccess(User $actor, User $user): bool
{
    if ($actor->is_admin) { return true; }
    // same-team member check...
}

// app/Policies/ActivityPolicy.php — delegation pattern
public function view(User $actor, Activity $activity): bool
{
    return $actor->can('view', $activity->user);  // delegates to UserPolicy
}
```

## Spatie Laravel Data (DTOs)

**Only create a DTO when the response shape is complex or combined** — it joins data from multiple models, carries computed values, or doesn't map directly to a single Eloquent model.

Simple model data is passed directly to `Inertia::render()` as an Eloquent model or collection; Wayfinder generates the TypeScript types via `App.Models.*`.

```php
// ✅ correct — simple model, pass directly
return Inertia::render('users/index', [
    'users' => $users->load('team'),
]);

// ✅ correct — complex cross-model shape uses a DTO
// app/Http/Controllers/TeamController.php
return Inertia::render('teams/settings', [
    'team' => $team ? TeamData::from([
        'id'      => $team->id,
        'name'    => $team->name,
        'members' => $team->members->map(fn (User $m) => new TeamMemberData(
            id:        $m->id,
            user_id:   $m->id,
            name:      $m->name,   // joined from users table
            role:      $m->pivot->role,
            joined_at: $m->pivot->joined_at?->toISOString(),
        ))->toArray(),
    ]) : null,
]);

// ❌ wrong — wrapping a single model in a DTO for no reason
return Inertia::render('users/index', [
    'users' => UserData::collect($users),
]);
```

DTOs are also used as **input normalizers** inside services, not just response shapes:

```php
// app/Services/UserService.php
private function normalizeSettings(array $data): array
{
    if (! isset($data['settings'])) { return $data; }
    $data['settings'] = SettingsData::from($data['settings'])->toArray();
    return $data;
}
```

When a DTO is created, run `composer generate:ts` to sync types in `resources/js/types/`.

## Migrations

### No DB Enums

Never use `$table->enum()`. Use `$table->string()` and enforce values via PHP-backed enum casts on the model.

```php
$table->string('status');   // cast to UserStatus::class on model
```

### No Magic Strings for Defaults

```php
use App\Enums\UserStatus;

$table->string('status')->default(UserStatus::Active->value);
```

### Column Order

1. `$table->id()`
2. Relation keys (`foreignId`) — unless the FK is tightly bound to adjacent data (e.g. morph pair)
3. Core / grouped data columns (name, amount, type, etc.)
4. Status, notes, JSON columns
5. `archived_at`, `softDeletes()`, then `timestamps()`

> **Note:** One legacy migration has `softDeletes()` before `timestamps()`. For new migrations follow the order above.

### Column Types

- `$table->decimal(15, 2)` for monetary amounts
- `$table->char('currency', 3)->default('USD')` for currency codes (ISO 4217)
- `$table->uuid('correlation_id')` for link/correlation IDs
- `$table->date()` for event dates (not `datetime`)
- `$table->smallInteger()` / `$table->tinyInteger()` for year/month columns

### Indexes

Declare explicit indexes for all foreign keys and any column used in `WHERE` or `ORDER BY`:

```php
// database/migrations/2026_01_01_000000_create_logins_table.php
$table->index('user_id');
$table->index(['user_id', 'login_date']);
$table->index(['user_id', 'type', 'login_date']);  // composite for reporting queries
$table->index('deleted_at');
```

## Caching

- Use Redis — supports cache tags
- Cache key pattern: `{type}:{scope}:{id}` e.g. `stats:user:42`
- Tag pattern: `Cache::tags(["user:{$id}"])->rememberForever($key, fn () => ...)`
- Invalidate via event listeners, not inline in services
- Treat cache as derived data — always maintain a fallback that recomputes from the database

```php
// app/Services/UserStatsService.php
return Cache::tags(["user:{$user->id}"])
    ->rememberForever("stats:user:{$user->id}", function () use ($user): string {
        // SQL aggregate query...
    });
```

## Artisan Commands

Commands delegate to a service and return `self::SUCCESS` / `self::FAILURE`:

```php
// app/Console/Commands/PruneStaleUsers.php
class PruneStaleUsers extends Command
{
    protected $signature = 'users:prune-stale';

    public function __construct(private readonly UserService $userService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->userService->pruneStale();

        $this->info("Pruned: {$result['pruned']}  Failed: {$result['failed']}");

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
```

## Shared Inertia Props

`HandleInertiaRequests::share()` exposes these props to every page:

```php
[
    'csrf_token'  => csrf_token(),
    'auth'        => [
        'user'        => fn () => $request->user()?->only('id', 'name', 'email', 'theme_preference'),
        'permissions' => fn () => $request->user()?->getPermissionsViaRoles()->pluck('name')->toArray(),
    ],
    'flash'       => [
        'type'    => fn () => $request->session()->get('type'),
        'message' => fn () => $request->session()->get('message'),
        'details' => fn () => $request->session()->get('details'),
    ],
    'meta'        => [
        'app_name'             => config('app.name'),
        'current_route_name'   => fn () => $method === 'GET' ? $request->route()->getName() : null,
        'previous_route_name'  => fn () => $method === 'GET' ? Route::getPreviousName() : null,
    ],
]
```

## Spatie Permission

User model uses `HasRoles` trait. Permissions are derived from roles (not assigned directly) and exposed via `auth.permissions` shared prop. A `GeneratePermissionTypes` Artisan command generates TypeScript types for permissions.

## Seeding

- Reference data (e.g. countries, statuses) ships in a dedicated seeder
- Per-record defaults (e.g. default settings for each new user) ship in their own seeder
- Run via `php artisan db:seed --class=...`

=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.4. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

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

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record a rule with `record-rule` only when the user explicitly asks for one. Instructions for the work at hand are not rules, no matter how emphatic: "remove this typo", "use X here" are work to do, not rules to record. Never record a rule on your own initiative, as a byproduct of a change, or to summarize what you just did. When the user does ask, pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Use `record-rule` rather than your native memory or notes tool, because native memory is personal and session-scoped, while only `.ai/rules` is shared with the team and persists in the repo.

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
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== herd rules ===

# Laravel Herd

- The application is served by Laravel Herd at `https?://[kebab-case-project-dir].test`. Use the `get-absolute-url` tool to generate valid URLs. Never run commands to serve the site. It is always available.
- Use the `herd` CLI to manage services, PHP versions, and sites (e.g. `herd sites`, `herd services:start <service>`, `herd php:list`). Run `herd list` to discover all available commands.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

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

This application uses Laravel Wayfinder to generate TypeScript from its Laravel code: route and controller-action functions, form request types, model interfaces, enums, Inertia page props, broadcast channels and events, and Vite environment variables.

- Generated files live under `resources/js/wayfinder` and are imported from `@/wayfinder/...`. Never hand-edit them; change the PHP and run `php artisan wayfinder:generate`.
- Import route functions from the path matching the controller's PHP namespace (`@/wayfinder/App/Http/Controllers/PostController`), named routes from `@/wayfinder/routes/<name>`, and every type from `@/wayfinder/types`.
- Import types rather than redeclaring them. A hand-written interface for a model, page props or a form request will drift.
- Keep anything that should not reach the browser out with the `#[WayfinderIgnore]` attribute, or a `@wayfinder-ignore` comment for an array key.

When working on Wayfinder itself — generating types, wiring the Vite plugin, choosing what to leave out, or debugging missing output — invoke `wayfinder-development` for detailed rules.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

=== inertia-svelte/core rules ===

# Inertia + Svelte

- IMPORTANT: Activate `inertia-svelte-development` when working with Inertia Svelte client-side patterns.

</laravel-boost-guidelines>

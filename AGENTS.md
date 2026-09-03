<laravel-boost-guidelines>
=== .ai/inertia-svelte-frontend rules ===

# Inertia + Svelte Frontend Rules

## Svelte 5 Runes

Always use Svelte 5 rune syntax. Never use the legacy Options API (`export let`, `$:`, reactive statements).

```svelte
<script lang="ts">
    // ✅ runes
    let { account, providers } = $props();
    let showConfirm = $state(false);
    const isEdit = $derived(!!account);
    $effect(() => {
        document.title = account.name;
    });

    // ❌ legacy Options API
    export let account;
    $: isEdit = !!account;
</script>
```

## File & Directory Naming

All `.svelte` and `.ts` files and their containing directories must use `kebab-case`.

- `account-form.svelte` ✅ — `AccountForm.svelte` ❌
- `use-theme.svelte.ts` ✅ — `useTheme.ts` ❌
- `components/account-list/` ✅ — `components/AccountList/` ❌

PascalCase is reserved for component names _inside_ files only (`<AccountForm />`).

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
        case name.startsWith('accounts'):
        case name.startsWith('transactions'):
        case name.startsWith('budgets'):
        case name.startsWith('categories'):
        case name.startsWith('household'):
        case name.startsWith('settings/theme'):
        case name.startsWith('transaction-presets'):
        case name.startsWith('recurring-presets'):
        case name.startsWith('reports'):
            return AppLayout;        // mobile bottom-nav layout

        case name.startsWith('dev'):
        case name.startsWith('dashboard'):
            return DashboardLayout;

        default:
            return null;             // auth pages use no layout
    }
},
```

`AppLayout` wraps content with a bottom nav and calls `useTheme()` + `useFlashToast()`. `DashboardLayout` is a sidebar + header layout that reads `page.props.meta.current_route_name` for breadcrumbs. Auth pages use no layout wrapper.

## Page Props

Use typed inline destructuring for `$props()`. Always import `App` from `@wayfinder/types`. Never use `generated.d.ts`.

```svelte
<!-- resources/js/pages/accounts/index.svelte -->
<script lang="ts">
    import type { App } from '@wayfinder/types';

    let { accounts }: { accounts: App.Models.Account[] } = $props();
</script>
```

For non-model prop shapes (e.g. paginator wrappers), define an inline interface:

```svelte
<!-- resources/js/pages/transactions/index.svelte -->
<script lang="ts">
    import type { App } from '@wayfinder/types';

    interface PaginatedTransactions {
        data: App.Models.Transaction[];
        links: { url: string | null; label: string; active: boolean }[];
        current_page: number;
        last_page: number;
    }

    let {
        account,
        transactions,
        balance,
    }: {
        account: App.Models.Account;
        transactions: PaginatedTransactions;
        balance: string;
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

Feature-specific components live in `resources/js/components/module/{module}/`. Each module directory groups all domain-specific UI: badge variants, forms, and any other domain UI.

Naming: `{module}-{purpose}.svelte`

```
components/module/
  account/
    account-access-type-badge.svelte
    account-form.svelte
    account-type-badge.svelte
  budget/
    budget-form.svelte
    budget-status-badge.svelte
  category/
    category-form.svelte
  household/
    household-form.svelte
    household-invite-form.svelte
    household-member-role-badge.svelte
  provider/
    provider-type-badge.svelte
  recurring-preset/
    recurring-frequency-badge.svelte
    recurring-preset-form.svelte
  report/
    category-leak-chart.svelte
    contribution-gauge.svelte
    credit-alert-badge.svelte
    credit-utilization-gauge.svelte
    trend-chart.svelte
  transaction/
    transaction-form.svelte
    transaction-type-badge.svelte
  transaction-preset/
    preset-form.svelte
    preset-type-badge.svelte
```

Pages import module components instead of inlining logic:

```svelte
<!-- resources/js/pages/accounts/create.svelte -->
<AccountForm {household_id} {providers} />

<!-- resources/js/pages/accounts/edit.svelte -->
<AccountForm
    {account}
    {providers}
    onCancel={() => router.visit(AccountsController.show.url({ account: account.id }))} />
```

## Enum Badge Components

Every PHP-backed enum must have a badge component in `components/module/{module}/`. Badges wrap `Badge` (`@components/ui/badge.svelte`) with a config map keyed by Wayfinder enum constants.

```svelte
<!-- resources/js/components/module/account/account-type-badge.svelte -->
<script lang="ts">
    import type { ColorVariant } from '@/data/theme';
    import type { App } from '@wayfinder/types';

    import AccountType from '@wayfinder/App/Enums/AccountType';

    import Badge from '@components/ui/badge.svelte';

    let { type }: { type: App.Enums.AccountType } = $props();

    const config: Record<App.Enums.AccountType, { label: string; color: ColorVariant }> = {
        [AccountType.DebitAccount]: { label: 'Debit', color: 'primary' },
        [AccountType.CreditCard]: { label: 'Credit Card', color: 'warning' },
        [AccountType.CashWallet]: { label: 'Cash', color: 'success' },
        [AccountType.EWallet]: { label: 'E-Wallet', color: 'info' },
        [AccountType.Investment]: { label: 'Investment', color: 'secondary' },
    };

    const badge = $derived(config[type]);
</script>

<Badge color={badge.color} variant="soft">{badge.label}</Badge>
```

Computed status badges not backed by a Wayfinder enum use a local string union type instead:

```svelte
<!-- resources/js/components/module/budget/budget-status-badge.svelte -->
type BudgetStatus = 'on_track' | 'at_risk' | 'over_budget';
let { status }: { status: BudgetStatus } = $props();
```

The config map uses Wayfinder constants as keys — TypeScript will error if an enum value changes without updating the map.

## Data Schemas (DataComposer)

One schema file per model in `resources/js/schema/`, named `{model}.schema.ts`. Schemas use the in-house `DataComposer` system (`@utilities/data-composer`). A single `DataSchema` drives form fields, display values, and table columns from one definition.

A schema file may export multiple schemas when a module has multiple distinct forms with different shapes (e.g. `household.schema.ts` exports both `householdSchema` and `householdInviteSchema`).

```typescript
// resources/js/schema/account.schema.ts
import type { DataSchema } from '@utilities/data-composer';
import type { App } from '@wayfinder/types';

import AccountType from '@wayfinder/App/Enums/AccountType';

export const accountSchema: DataSchema<App.Models.Account> = {
    name: {
        label: 'Name',
        table: true, // include in toDatatableColumn()
        form: () => ({
            type: 'text',
            name: 'name',
            required: true,
            inputProps: { placeholder: 'e.g. BCA Savings', autocorrect: 'off' },
        }),
    },
    initial_balance: {
        label: 'Initial Balance',
        value: (data) => Number(data.initial_balance).toLocaleString('id-ID'), // display formatter
        form: () => ({
            type: 'number',
            name: 'initial_balance',
            inputProps: { inputmode: 'decimal', min: 0, step: 0.01 },
        }),
    },
    credit_card_limit: {
        label: 'Credit Limit',
        show: (data) => data.type === AccountType.CreditCard, // conditional display
        form: () => ({
            type: 'number',
            name: 'credit_card_limit',
            show: (form: any) => form.type === AccountType.CreditCard, // conditional form field
            inputProps: { inputmode: 'decimal', min: 0, step: 0.01 },
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
<!-- resources/js/components/module/account/account-form.svelte -->
<script lang="ts">
    import { accountSchema } from '@schema/account.schema';

    import { DataComposer } from '@utilities/data-composer';

    // formSchema is a $derived wrapping a function — invoke in the template: formSchema()
    const formSchema = $derived(() => {
        const composer = DataComposer.from(accountSchema).extendSchema({
            provider_id: {
                label: 'Provider (optional)',
                form: () => ({ type: 'select', name: 'provider_id', options: providerOptions }),
            },
        });

        if (isEdit && account) {
            return composer.except(['access_type', 'initial_balance']).toFormGenerator({
                name: account.name,
                type: account.type,
                provider_id: account.provider_id ?? '',
            });
        }

        const { fields, data } = composer.toFormGenerator({
            type: AccountType.DebitAccount,
            access_type: AccountAccessType.Personal,
            initial_balance: 0,
        });

        // Hidden values not in fields are still submitted
        return { fields, data: { ...data, household_id: household_id ?? '' } };
    });
</script>

<FormGenerator formSchema={formSchema()} ... />
```

`$derived(() => { ... })` (a derived wrapping a function factory) is used instead of plain `$derived(...)` when the expression calls `DataComposer.from()` and may need to re-evaluate as reactive dependencies change. Always invoke the result when passing to a prop: `formSchema={formSchema()}`.

```svelte
<!-- resources/js/pages/accounts/show.svelte -->
<script lang="ts">
    // Display: feed DataList
    const details = $derived(
        DataComposer.from(accountSchema)
            .except(['type', 'access_type', 'name'])
            .toDataDisplay(account)
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
        id="account-form"
        {action}
        formSchema={formSchema()}
        method="put"
        withoutSubmit
        bind:form />
</Card>

<div class="mt-4">
    <FormAction
        {form}
        formId="account-form"
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
- `data` keys not in `fields` are still submitted (use for hidden values like `household_id`)
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
    action={HouseholdInvitationsController.accept.url({ token: invitation.token })}>
    <SubmitButton class="w-full" submitting={acceptForm.processing}>Accept Invitation</SubmitButton>
</Form>
```

`Form` defaults to `method="post"` and `preserveScroll: true`. Pass `method` for other verbs. It injects a hidden `_method` field for method spoofing (PUT/PATCH/DELETE).

### FormAction

`FormAction` (`@components/ui/forms/form-action.svelte`) renders a Cancel + Submit button row:

```svelte
<FormAction
    {form}
    formId="account-form"
    labelSubmit="Create Account"
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
<!-- resources/js/pages/accounts/edit.svelte -->
<script lang="ts">
    let showDeleteConfirm = $state(false);

    function destroy() {
        router.delete(AccountsController.destroy.url({ account: account.id }));
    }
</script>

<ConfirmationModal
    title="Delete Account"
    confirmText="Delete"
    cancelText="Cancel"
    confirmButtonProps={{ color: 'error' }}
    onConfirm={destroy}
    bind:open={showDeleteConfirm}>
    This will permanently delete the account and cannot be undone.
</ConfirmationModal>

<Button color="error" variant="outline" onclick={() => (showDeleteConfirm = true)}>Delete</Button>
```

Pattern with nullable ID state (for list items):

```svelte
<!-- resources/js/pages/budgets/index.svelte -->
let deletingBudgetId = $state<number | null>(null);

function destroyBudget() {
    if (!deletingBudgetId) return;
    router.delete(BudgetsController.destroy.url({ account: account.id, budget: deletingBudgetId }), {
        onFinish: () => (deletingBudgetId = null),
    });
}

<ConfirmationModal
    onCancel={() => (deletingBudgetId = null)}
    onConfirm={destroyBudget}
    bind:open={deletingBudgetId}>
    ...
</ConfirmationModal>
```

### DetailActionModal

`DetailActionModal` (`@components/ui/modals/detail-action-modal.svelte`) supports a view/edit mode toggle in a single modal. The `children` snippet receives the current `mode`:

```svelte
<DetailActionModal
    bind:open={showModal}
    bind:mode
    title="Transaction"
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
<Button href={AccountsController.create.url()} color="primary" size="sm">
    <i class="iconify size-4 ph--plus-bold"></i> Add
</Button>

<!-- Circle icon button -->
<Button class="btn-circle btn-sm" color="light" variant="ghost">
    <i class="iconify size-5 ph--arrow-left-bold"></i>
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
<Card title="Account Details">content</Card>

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
            <AccountTypeBadge type={account.type} />
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
import AccountType from '@wayfinder/App/Enums/AccountType';
import AccountsController from '@wayfinder/App/Http/Controllers/AccountsController';
// Named routes (rarely needed; prefer controller imports)
import accounts from '@wayfinder/routes/accounts';
```

### URL generation

```typescript
AccountsController.index.url(); // '/accounts'
AccountsController.show.url({ account: 1 }); // '/accounts/1'
AccountsController.index.url({ query: { page: 2 } }); // '/accounts?page=2'
```

### With Inertia — always use `.url()`

```typescript
// FormGenerator / Form component action prop
action={AccountsController.store.url()}
action={AccountsController.update.url({ account: account.id })}

// router for non-form navigation and actions
router.delete(CategoriesController.destroy.url({ category: id }));
router.post(AccountsController.archive.url({ account: id }));
router.visit(
    BudgetsController.index.url({ account: id, query: { year, month } }),
    { preserveState: false }
);
```

### `.form()` — native HTML forms only

```typescript
// Produces { action: '/accounts/1?_method=PUT', method: 'post' }
// Only use when spreading onto a native <form> — NOT with Inertia Form/FormGenerator
AccountsController.update.form({ account: 1 });
```

### Enum constants — no magic strings

```typescript
// ✅ correct
if (account.type === AccountType.CreditCard) { ... }
const form = useForm({ type: AccountType.DebitAccount });

// ❌ wrong
if (account.type === 'credit_card') { ... }
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

`useTheme()` and `useFlashToast()` are called inside `AppLayout` and `DashboardLayout` — do not call them again in individual page components.

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

Icons use the `iconify` CSS class with Phosphor icons (`ph--*`). The `-bold` suffix is the standard weight:

```svelte
<i class="iconify size-5 ph--arrow-left-bold"></i>
<i class="iconify size-4 ph--plus-bold"></i>
<i class="iconify size-12 ph--wallet-bold"></i>
```

Use DaisyUI size utilities: `size-4`, `size-5`, `size-6`, `size-10`, `size-12`.

=== .ai/laravel-backend rules ===

# Laravel Backend Rules

## PHP Conventions

- PHP 8.4 — use constructor property promotion, readonly properties, first-class callables
- Always declare explicit return types and typed parameters: `function create(User $user, array $data): Account`
- Use curly braces on all control structures, even single-line bodies
- Enums: backed string enums, TitleCase case names — `case DebitAccount = 'debit_account'`
- PHPDoc blocks for complex return types (e.g. `@return array{executed: int, failed: int}`); inline comments only for non-obvious invariants

## Architecture Patterns

### Service Pattern

All business logic lives in `app/Services/`. Controllers are thin dispatchers that call one service method and return an Inertia response. Never put queries, calculations, or conditional logic directly in a controller.

```php
// ✅ correct — app/Http/Controllers/AccountsController.php
class AccountsController extends Controller
{
    public function __construct(private readonly AccountService $accountService) {}

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $account = $this->accountService->create($request->user(), $request->validated());
        return to_route('accounts.show', $account)->flash('Account created.');
    }
}

// ❌ wrong — logic in controller
public function store(Request $request): RedirectResponse
{
    $account = Account::create([...$request->validated(), 'owner_id' => $request->user()->id]);
}
```

Services can inject other services when needed:

```php
// app/Services/RecurringPresetService.php
class RecurringPresetService
{
    public function __construct(private readonly TransactionService $transactionService) {}
}
```

### Event-Listener Pattern

Side-effects (cache invalidation, notifications, post-save hooks) live in Listeners attached to Events — never inline inside a service method. Services fire an event after the primary action; listeners react.

```php
// app/Services/TransactionService.php — service fires event, does NOT touch cache
class TransactionService
{
    public function create(Account $account, User $creator, array $data): Transaction
    {
        $transaction = Transaction::create([...]);
        TransactionSaved::dispatch($transaction);
        return $transaction;
    }
}

// app/Listeners/InvalidateAccountBalanceCache.php — listener owns the side-effect
class InvalidateAccountBalanceCache
{
    // Union type — one handler for two related events
    public function handle(TransactionSaved | TransactionDeleted $event): void
    {
        Cache::tags(['account:' . $event->transaction->account_id])->flush();
    }

    // Named handler for a third, structurally different event on the same listener
    public function handleRecurringPresetExecuted(RecurringPresetExecuted $event): void
    {
        Cache::tags(['account:' . $event->preset->account_id])->flush();
    }
}
```

### DB Transactions for Multi-Step Writes

Wrap operations that must succeed or fail together in `DB::transaction()`:

```php
// app/Services/TransactionService.php
public function createTransfer(...): Transaction
{
    $linkId = (string) Str::uuid();

    return DB::transaction(function () use (...): Transaction {
        $outflow = $this->create($sourceAccount, $creator, [...]);
        $this->create($destinationAccount, $creator, [...]);

        if ($feeAmount !== null && $feeAmount > 0) {
            $this->create($sourceAccount, $creator, [...]);
        }

        return $outflow;
    });
}
```

Each iteration of a loop that can partially fail should wrap its own `DB::transaction` with a try/catch (see `RecurringPresetService::runDue()`).

### Aggregates via SQL — Never PHP

Balance calculations, budget spend, report totals, and any sum/count over rows must be computed using SQL aggregates. Never fetch a collection and reduce it in PHP.

```php
// ✅ correct — app/Services/BalanceService.php
$balance = DB::table('accounts')
    ->selectRaw(
        'accounts.initial_balance + COALESCE(SUM(CASE
            WHEN t.type IN (?, ?) THEN t.amount
            WHEN t.type IN (?, ?, ?) THEN -t.amount
            ELSE 0
        END), 0) AS balance',
        ['income', 'transfer_in', 'expense', 'transfer_out', 'fee']
    )
    ->leftJoin('transactions as t', fn ($join) => $join->on('t.account_id', '=', 'accounts.id')->whereNull('t.deleted_at'))
    ->where('accounts.id', $account->id)
    ->groupBy('accounts.id', 'accounts.initial_balance')
    ->value('balance');

// ❌ wrong — PHP reduction
$balance = $account->initial_balance;
foreach ($account->transactions as $t) { ... }
```

## Controllers

- Return `Inertia::render('page/name', [...])` passing **raw Eloquent models** — not DTOs (DTOs only for cross-model shapes, see Spatie Data section)
- Use `$this->authorize()` for every write action and `view` checks on resource pages
- Use `abort_unless()` for quick business-rule guards that don't warrant a policy
- Use `to_route()` for redirects after mutations; use `back()` when there's no single canonical destination
- Inject services via constructor property promotion

```php
// to_route() — after create/update/destroy with a known destination
return to_route('accounts.show', $account)->flash('Account created.');

// back() — after actions like invite/remove where destination varies
return back()->flash('Invitation sent.');

// abort_unless() — quick guard before policy check
abort_unless($membership !== null, 403);
$this->authorize('invite', $household);
```

Authorize with extra model context (second arg to `[Transaction::class, $account]`):

```php
// app/Http/Controllers/TransactionsController.php
$this->authorize('viewAny', [Transaction::class, $account]);
$this->authorize('create', [Transaction::class, $account]);
```

## Form Requests

All validation lives in `app/Http/Requests/`. Never use inline `$request->validate()`.

```php
class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:255'],
            'type'            => ['required', 'string', Rule::enum(AccountType::class)],
            'initial_balance' => ['required', 'numeric', 'min:0'],
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
// app/Models/Account.php
class Account extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type'         => AccountType::class,
            'access_type'  => AccountAccessType::class,
            'initial_balance' => 'decimal:2',
            'archived_at'  => 'datetime',
            'decorations'    => 'array',
        ];
    }

    #[Scope]
    protected function visibleTo(Builder $query, User $user): Builder
    {
        // ...returns query filtered to accounts the user can see
    }
}

// app/Models/TransactionRecurringPreset.php
// Domain behavior on the model (date math)
public function advanceNextRunDate(Carbon $from): Carbon
{
    return match ($this->frequency) {
        RecurringFrequency::Daily  => $from->addDay(),
        RecurringFrequency::Weekly => $from->addWeek(),
        // ...
    };
}

// Disable timestamps when not needed
// app/Models/HouseholdMember.php
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
// app/Enums/TransactionType.php
enum TransactionType: string
{
    case Income      = 'income';
    case Expense     = 'expense';
    case TransferOut = 'transfer_out';
    case TransferIn  = 'transfer_in';
    case Fee         = 'fee';

    /** @return array<string> */
    public static function inflows(): array
    {
        return [self::Income->value, self::TransferIn->value];
    }

    /** @return array<string> */
    public static function outflows(): array
    {
        return [self::Expense->value, self::TransferOut->value, self::Fee->value];
    }

    /** @return array<string> Types that count toward budget spend */
    public static function spendTypes(): array
    {
        return [self::Expense->value, self::Fee->value];
    }
}
```

## Policies

Every resource that requires authorization has a Policy. Register automatically via model discovery.

- Extract shared access logic into a private `canAccess()` method
- Delegate to a related model's policy via `$user->can('view', $relatedModel)` rather than re-implementing ownership checks
- Custom methods beyond CRUD are fine: `archive`, `restore`, `invite`, `removeMember`, `toggle`

```php
// app/Policies/AccountPolicy.php — custom method + private extractor
public function archive(User $user, Account $account): bool
{
    return $account->owner_id === $user->id;
}

private function canAccess(User $user, Account $account): bool
{
    if ($account->owner_id === $user->id) { return true; }
    // joint account + household member check...
}

// app/Policies/TransactionPolicy.php — delegation pattern
public function view(User $user, Transaction $transaction): bool
{
    return $user->can('view', $transaction->account);  // delegates to AccountPolicy
}
```

## Spatie Laravel Data (DTOs)

**Only create a DTO when the response shape is complex or combined** — it joins data from multiple models, carries computed values, or doesn't map directly to a single Eloquent model.

Simple model data is passed directly to `Inertia::render()` as an Eloquent model or collection; Wayfinder generates the TypeScript types via `App.Models.*`.

```php
// ✅ correct — simple model, pass directly
return Inertia::render('accounts/index', [
    'accounts' => $accounts->load('provider'),
]);

// ✅ correct — complex cross-model shape uses a DTO
// app/Http/Controllers/HouseholdsController.php
return Inertia::render('household/settings', [
    'household' => $household ? HouseholdData::from([
        'id'      => $household->id,
        'name'    => $household->name,
        'members' => $household->members->map(fn (HouseholdMember $m) => new HouseholdMemberData(
            id:        $m->id,
            user_id:   $m->user_id,
            name:      $m->user->name,   // joined from users table
            role:      $m->role,
            joined_at: $m->joined_at?->toISOString(),
        ))->toArray(),
    ]) : null,
]);

// ❌ wrong — wrapping a single model in a DTO for no reason
return Inertia::render('accounts/index', [
    'accounts' => AccountData::collect($accounts),
]);
```

DTOs are also used as **input normalizers** inside services, not just response shapes:

```php
// app/Services/AccountService.php
private function normalizeDecorations(array $data): array
{
    if (! isset($data['decorations'])) { return $data; }
    $data['decorations'] = DecorationData::from($data['decorations'])->toArray();
    return $data;
}
```

When a DTO is created, run `composer generate:ts` to sync types in `resources/js/types/`.

## Migrations

### No DB Enums

Never use `$table->enum()`. Use `$table->string()` and enforce values via PHP-backed enum casts on the model.

```php
$table->string('type');   // cast to TransactionType::class on model
```

### No Magic Strings for Defaults

```php
use App\Enums\ProviderStatus;

$table->string('status')->default(ProviderStatus::Active->value);
```

### Column Order

1. `$table->id()`
2. Relation keys (`foreignId`) — unless the FK is tightly bound to adjacent data (e.g. morph pair)
3. Core / grouped data columns (name, amount, type, etc.)
4. Status, notes, JSON columns
5. `archived_at`, `softDeletes()`, then `timestamps()`

> **Note:** The `transaction_recurring_presets` migration has `softDeletes()` before `timestamps()`. For new migrations follow the order above.

### Column Types

- `$table->decimal(15, 2)` for monetary amounts
- `$table->char('currency', 3)->default('IDR')` for currency codes
- `$table->uuid('transfer_link_id')` for link/correlation IDs
- `$table->date()` for transaction/event dates (not `datetime`)
- `$table->smallInteger()` / `$table->tinyInteger()` for year/month columns

### Indexes

Declare explicit indexes for all foreign keys and any column used in `WHERE` or `ORDER BY`:

```php
// app/database/migrations/2026_06_16_161919_create_transactions_table.php
$table->index('account_id');
$table->index(['account_id', 'transaction_date']);
$table->index(['account_id', 'type', 'transaction_date']);  // composite for reporting queries
$table->index('deleted_at');
```

## Caching

- Use Redis — supports cache tags
- Cache key pattern: `{type}:{scope}:{id}` e.g. `balance:account:42`
- Tag pattern: `Cache::tags(["account:{$id}"])->rememberForever($key, fn () => ...)`
- Invalidate via event listeners, not inline in services
- Treat cache as derived data — always maintain a fallback that recomputes from the database

```php
// app/Services/BalanceService.php
return Cache::tags(["account:{$account->id}"])
    ->rememberForever("balance:account:{$account->id}", function () use ($account): string {
        // SQL aggregate query...
    });
```

## Artisan Commands

Commands delegate to a service and return `self::SUCCESS` / `self::FAILURE`:

```php
// app/Console/Commands/RunRecurringPresets.php
class RunRecurringPresets extends Command
{
    protected $signature = 'presets:run-recurring';

    public function __construct(private readonly RecurringPresetService $recurringPresetService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->recurringPresetService->runDue();

        $this->info("Executed: {$result['executed']}  Failed: {$result['failed']}");

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

- `ProviderSeeder` — seeds reference data (banks, e-wallets)
- `CategorySeeder` — seeds 2-level default category hierarchy per user
- Run via `php artisan db:seed --class=ProviderSeeder`

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
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

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

=== herd rules ===

# Laravel Herd

- The application is served by Laravel Herd at `https?://[kebab-case-project-dir].test`. Use the `get-absolute-url` tool to generate valid URLs. Never run commands to serve the site. It is always available.
- Use the `herd` CLI to manage services, PHP versions, and sites (e.g. `herd sites`, `herd services:start <service>`, `herd php:list`). Run `herd list` to discover all available commands.

=== tests rules ===

# Test Enforcement

- Test every code change by adding or updating a test.
- Run the affected tests and ensure they pass.
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

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== wayfinder/v rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

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

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
